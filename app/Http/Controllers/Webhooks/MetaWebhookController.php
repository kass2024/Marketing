<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Message;
use App\Models\PlatformMetaConnection;
use App\Services\Chatbot\ChatbotProcessor;
use App\Services\Chatbot\MessageDispatcher;
use App\Services\Chatbot\SpeechService;
use App\Services\WhatsAppAudioConverter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class MetaWebhookController extends Controller
{
    public function __construct(
        protected ChatbotProcessor $processor,
        protected MessageDispatcher $dispatcher
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Webhook Verification
    |--------------------------------------------------------------------------
    */
    public function verify(Request $request): Response
    {
        $mode = $request->input('hub_mode') ?? $request->input('hub.mode');
        $token = $request->input('hub_verify_token') ?? $request->input('hub.verify_token');
        $challenge = $request->input('hub_challenge') ?? $request->input('hub.challenge');

        if (
            $mode === 'subscribe' &&
            hash_equals(
                (string) config('services.whatsapp_webhook.verify_token'),
                (string) $token
            )
        ) {
            return response($challenge, 200);
        }

        Log::warning('Meta webhook verification failed');

        return response('Forbidden', 403);
    }

    /*
    |--------------------------------------------------------------------------
    | Handle Incoming Webhook
    |--------------------------------------------------------------------------
    */
    public function handle(Request $request): Response
    {
        if (! $this->isValidSignature($request)) {
            Log::warning('Invalid Meta webhook signature');

            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $payload = $request->json()->all();

        if (($payload['object'] ?? null) !== 'whatsapp_business_account') {
            return response()->json(['status' => 'ignored'], 200);
        }

        $forwardParrot = false;

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {

                $value = $change['value'] ?? [];
                $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;
                $routeParrot = $this->isParrotSupportPhoneNumberId($phoneNumberId);

                if (! empty($value['messages'])) {
                    if ($routeParrot) {
                        $forwardParrot = true;

                        continue;
                    }
                    $this->handleIncomingMessages($value);
                }

                if (! empty($value['statuses'])) {
                    if ($routeParrot) {
                        $forwardParrot = true;

                        continue;
                    }
                    $this->handleStatusUpdates($value['statuses']);
                }
            }
        }

        if ($forwardParrot) {
            $this->forwardToParrotSupport($request);
        }

        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * Same Meta app: Parrot must receive the original body + signature.
     * Called at most once per request when any change targets a Parrot line.
     */
    protected function forwardToParrotSupport(Request $request): void
    {
        $url = config('services.parrot_support.forward_url');
        if (! is_string($url) || $url === '') {
            Log::error('PARROT_WEBHOOK_FORWARD_URL is not set; Parrot support events are dropped');

            return;
        }

        $sigHeader = config('services.whatsapp_webhook.signature_header', 'X-Hub-Signature-256');
        $signature = $request->header($sigHeader);
        $headers = ['Content-Type' => 'application/json'];
        if (is_string($signature) && $signature !== '') {
            $headers[$sigHeader] = $signature;
        } else {
            Log::warning('Parrot webhook forward missing Meta signature header; Parrot may return 403');
        }

        try {
            $raw = $request->getContent();
            if ($raw === '') {
                Log::error('Parrot webhook forward aborted: empty request body');
                return;
            }

            $response = Http::timeout(20)
                ->withHeaders($headers)
                ->withBody($raw, 'application/json')
                ->post($url);

            if ($response->successful()) {
                Log::info('Parrot webhook forward ok', ['status' => $response->status()]);
            } else {
                Log::warning('Parrot webhook forward failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Parrot webhook forward exception', ['e' => $e->getMessage()]);
        }
    }

    /**
     * Meta JSON often encodes phone_number_id as a number; normalize to string for matching .env lists.
     */
    protected function isParrotSupportPhoneNumberId(mixed $phoneNumberId): bool
    {
        if ($phoneNumberId === null || $phoneNumberId === '') {
            return false;
        }

        if (! is_scalar($phoneNumberId)) {
            return false;
        }

        $id = trim((string) $phoneNumberId);
        if ($id === '') {
            return false;
        }

        $ids = config('services.parrot_support.phone_number_ids', []);
        if (! is_array($ids)) {
            return false;
        }

        foreach ($ids as $configured) {
            if (is_scalar($configured) && trim((string) $configured) === $id) {
                return true;
            }
        }

        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | Process Incoming Messages
    |--------------------------------------------------------------------------
    */
    protected function handleIncomingMessages(array $value): void
    {
        $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;

        if (! $phoneNumberId) {
            Log::warning('Missing phone_number_id in webhook');

            return;
        }

        $platform = PlatformMetaConnection::where(
            'whatsapp_phone_number_id',
            $phoneNumberId
        )->first();

        if (! $platform) {
            Log::warning('Platform not found', ['phone_number_id' => $phoneNumberId]);

            return;
        }

        $clientId = $this->resolveClientId($platform);
        if (! $clientId) {
            return;
        }

        foreach ($value['messages'] as $incoming) {

            $from = $incoming['from'] ?? null;
            $messageId = $incoming['id'] ?? null;

            if (! $from || ! $messageId) {
                continue;
            }

            if ($this->isDuplicate($messageId)) {
                continue;
            }

            $payload = $this->extractInboundPayload($incoming);

            $text = $payload['text'] ?? '';
            $inboundMediaUrl = null;
            $inboundMediaType = null;
            $inboundFilename = null;
            $inboundDisplayContent = null;

            $voiceFallbackInstruction = 'The user sent a voice message. Transcription was unavailable. Greet them briefly and ask them to type their question, or summarize common topics you can help with (visas, study abroad, etc.).';

            if ($text === '' && ! empty($payload['audio_media_id'])) {
                $voiceNote = $this->processInboundVoiceNote($platform, $payload['audio_media_id']);
                if ($voiceNote) {
                    $inboundMediaUrl = $voiceNote['media_url'];
                    $inboundMediaType = $voiceNote['media_type'];
                    $inboundFilename = $voiceNote['filename'];
                    $transcribed = trim((string) ($voiceNote['text'] ?? ''));
                    if ($transcribed !== '') {
                        $text = $transcribed;
                        $inboundDisplayContent = $transcribed;
                    } else {
                        Log::warning('Voice note could not be transcribed; using fallback text for bot', [
                            'from' => $from,
                            'media_id' => $payload['audio_media_id'],
                            'stored_url' => $inboundMediaUrl,
                        ]);
                        $text = $voiceFallbackInstruction;
                        $inboundDisplayContent = '🎤 Voice note';
                    }
                } elseif (config('chatbot.transcribe_inbound_audio', true)) {
                    $legacyText = trim((string) ($this->legacyTranscribeWithoutStorage($platform, $payload['audio_media_id']) ?? ''));
                    if ($legacyText !== '') {
                        $text = $legacyText;
                        $inboundDisplayContent = $legacyText;
                    } else {
                        Log::warning('Voice note download/transcribe failed entirely', [
                            'from' => $from,
                            'media_id' => $payload['audio_media_id'],
                        ]);
                        $text = $voiceFallbackInstruction;
                        $inboundDisplayContent = '🎤 Voice note';
                    }
                } else {
                    $text = $voiceFallbackInstruction;
                    $inboundDisplayContent = '🎤 Voice note';
                }
            }

            if ($text === '') {
                Log::info('Unsupported or empty inbound message', ['type' => $incoming['type'] ?? null]);

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Ad Attribution Detection (Enterprise Upgrade)
            |--------------------------------------------------------------------------
            */
            $referral = $incoming['referral'] ?? null;

            $metaCampaignId = null;
            $metaAdsetId = null;
            $metaAdId = null;
            $source = 'organic';

            if ($referral) {
                $metaCampaignId = $referral['campaign_id'] ?? null;
                $metaAdsetId = $referral['adset_id'] ?? null;
                $metaAdId = $referral['ad_id'] ?? null;
                $source = 'paid';

                Log::info('Ad referral detected', [
                    'campaign_id' => $metaCampaignId,
                    'adset_id' => $metaAdsetId,
                    'ad_id' => $metaAdId,
                ]);
            }

            try {

                $aiResponse = $this->processor->process([
                    'from' => $from,
                    'text' => $text,
                    'client_id' => $clientId,
                    'message_id' => $messageId,
                    'meta_campaign_id' => $metaCampaignId,
                    'meta_adset_id' => $metaAdsetId,
                    'meta_ad_id' => $metaAdId,
                    'source' => $source,
                    'inbound_media_url' => $inboundMediaUrl,
                    'inbound_media_type' => $inboundMediaType,
                    'inbound_filename' => $inboundFilename,
                    'inbound_display_content' => $inboundDisplayContent,
                ]);

                if (empty($aiResponse) || empty($aiResponse['text'])) {
                    continue;
                }

                if (config('chatbot.voice_faq_replies') && $this->shouldAttachVoiceReply($aiResponse)) {
                    Log::channel('voice')->info('Outbound TTS: attempting', [
                        'source' => $aiResponse['source'] ?? null,
                        'text_len' => strlen($aiResponse['text'] ?? ''),
                    ]);
                    $path = app(SpeechService::class)->textToSpeechMp3($aiResponse['text']);
                    if ($path) {
                        $aiResponse['voice_url'] = URL::to(Storage::disk('public')->url($path));
                        Log::channel('voice')->info('Outbound TTS: file ready', [
                            'path' => $path,
                            'voice_url' => $aiResponse['voice_url'],
                        ]);
                    } else {
                        Log::channel('voice')->warning('Outbound TTS: SpeechService returned null (sending text only)', [
                            'source' => $aiResponse['source'] ?? null,
                        ]);
                    }
                } elseif (config('chatbot.voice_faq_replies')) {
                    Log::channel('voice')->debug('Outbound TTS: skipped for source', [
                        'source' => $aiResponse['source'] ?? null,
                        'allowed' => config('chatbot.voice_reply_sources', []),
                    ]);
                } else {
                    Log::channel('voice')->debug('Outbound TTS: CHATBOT_VOICE_FAQ_REPLIES is false');
                }

                $results = $this->dispatcher->send(
                    platform: $platform,
                    to: $from,
                    payload: $aiResponse
                );

                $this->storeExternalIds($results);

            } catch (\Throwable $e) {

                Log::error('Incoming message processing failed', [
                    'error' => $e->getMessage(),
                    'from' => $from,
                ]);
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Handle Delivery Status Updates
    |--------------------------------------------------------------------------
    */
    protected function handleStatusUpdates(array $statuses): void
    {
        foreach ($statuses as $status) {

            $externalId = $status['id'] ?? null;
            $delivery = $status['status'] ?? null;

            if (! $externalId || ! $delivery) {
                continue;
            }

            Message::where('external_message_id', $externalId)
                ->update(['status' => $delivery]);

            Log::info('Message status updated', [
                'external_id' => $externalId,
                'status' => $delivery,
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Store External IDs Safely
    |--------------------------------------------------------------------------
    */
    protected function storeExternalIds(array $results): void
    {
        foreach ($results as $result) {

            if (! empty($result['external_message_id'])) {

                Message::whereNull('external_message_id')
                    ->latest('id')
                    ->limit(1)
                    ->update([
                        'external_message_id' => $result['external_message_id'],
                        'status' => 'sent',
                    ]);
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Resolve Client
    |--------------------------------------------------------------------------
    */
    protected function resolveClientId(PlatformMetaConnection $platform): ?int
    {
        $userId = $platform->connected_by;

        $clientId = Client::where('user_id', $userId)->value('id');

        if (! $clientId) {
            Log::error('Client not found for platform', [
                'platform_id' => $platform->id,
            ]);
        }

        return $clientId;
    }

    /*
    |--------------------------------------------------------------------------
    | Extract inbound payload (text and/or audio id)
    |--------------------------------------------------------------------------
    */
    protected function extractInboundPayload(array $incoming): array
    {
        $type = $incoming['type'] ?? null;

        return match ($type) {
            'text' => [
                'text' => trim($incoming['text']['body'] ?? ''),
            ],
            'button' => [
                'text' => trim($incoming['button']['text'] ?? ''),
            ],
            'interactive' => [
                'text' => trim(
                    $incoming['interactive']['button_reply']['title']
                    ?? $incoming['interactive']['list_reply']['title']
                    ?? ''
                ),
            ],
            'audio' => [
                'audio_media_id' => $incoming['audio']['id'] ?? null,
            ],
            // Some payloads label voice distinctly; treat like audio.
            'voice' => [
                'audio_media_id' => is_array($incoming['voice'] ?? null)
                    ? ($incoming['voice']['id'] ?? null)
                    : null,
            ],
            default => [],
        };
    }

    protected function shouldAttachVoiceReply(array $aiResponse): bool
    {
        $source = (string) ($aiResponse['source'] ?? '');
        $allowed = config('chatbot.voice_reply_sources', []);

        return $source !== '' && in_array($source, $allowed, true);
    }

    /**
     * Download WhatsApp voice/audio to a temp file. Caller must unlink path when done.
     *
     * @return array{path: string, mime: string, ext: string}|null
     */
    protected function fetchInboundAudioTemporaryFile(PlatformMetaConnection $platform, string $mediaId): ?array
    {
        $token = $this->dispatcher->accessTokenForPlatform($platform);
        if (! $token) {
            Log::warning('No access token for media download', ['platform_id' => $platform->id]);
            Log::channel('voice')->warning('Inbound transcribe: no platform access token', ['platform_id' => $platform->id]);

            return null;
        }

        $base = rtrim((string) config('services.whatsapp.graph_url'), '/');
        $version = trim((string) config('services.whatsapp.graph_version'), '/');

        try {
            $metaResponse = Http::withToken($token)
                ->timeout(45)
                ->get("{$base}/{$version}/{$mediaId}");

            if ($metaResponse->failed()) {
                Log::warning('WhatsApp media meta failed', [
                    'status' => $metaResponse->status(),
                    'body' => $metaResponse->body(),
                ]);
                Log::channel('voice')->warning('Inbound transcribe: media meta HTTP failed', [
                    'media_id' => $mediaId,
                    'status' => $metaResponse->status(),
                    'body' => $metaResponse->body(),
                ]);

                return null;
            }

            $mime = (string) ($metaResponse->json('mime_type') ?? '');
            $mediaUrl = $metaResponse->json('url');
            if (! $mediaUrl) {
                Log::warning('WhatsApp media meta missing url', ['json' => $metaResponse->json()]);
                Log::channel('voice')->warning('Inbound transcribe: media meta missing url', [
                    'media_id' => $mediaId,
                    'json' => $metaResponse->json(),
                ]);

                return null;
            }

            $ext = match (true) {
                str_contains($mime, 'ogg') => 'ogg',
                str_contains($mime, 'opus') => 'ogg',
                str_contains($mime, 'mpeg') || str_contains($mime, 'mp3') => 'mp3',
                str_contains($mime, 'mp4') || str_contains($mime, 'm4a') || str_contains($mime, 'aac') => 'm4a',
                str_contains($mime, 'webm') => 'webm',
                str_contains($mime, 'wav') => 'wav',
                str_contains($mime, 'amr') => 'amr',
                default => 'bin',
            };

            $binary = Http::withToken($token)
                ->timeout(120)
                ->get($mediaUrl);

            if ($binary->failed()) {
                Log::warning('WhatsApp media binary download failed', ['status' => $binary->status()]);
                Log::channel('voice')->warning('Inbound transcribe: binary download failed', [
                    'media_id' => $mediaId,
                    'status' => $binary->status(),
                ]);

                return null;
            }

            $tmp = sys_get_temp_dir().'/wa_audio_'.uniqid('', true).'.'.$ext;
            if (file_put_contents($tmp, $binary->body()) === false) {
                Log::channel('voice')->warning('Inbound transcribe: could not write temp file', ['media_id' => $mediaId]);

                return null;
            }

            $bytes = @filesize($tmp) ?: 0;
            Log::channel('voice')->info('Inbound transcribe: downloaded', [
                'media_id' => $mediaId,
                'mime' => $mime,
                'ext' => $ext,
                'bytes' => $bytes,
            ]);

            return ['path' => $tmp, 'mime' => $mime, 'ext' => $ext];
        } catch (\Throwable $e) {
            Log::error('Audio download failed', ['error' => $e->getMessage()]);
            Log::channel('voice')->error('Inbound transcribe: download exception', [
                'media_id' => $mediaId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Store inbound voice on public disk, transcribe for the AI, return URLs for the inbox UI.
     *
     * @return array{text: string, media_url: string, media_type: string, filename: string}|null
     */
    protected function processInboundVoiceNote(PlatformMetaConnection $platform, string $mediaId): ?array
    {
        $f = $this->fetchInboundAudioTemporaryFile($platform, $mediaId);
        if (! $f) {
            return null;
        }

        $tmp = $f['path'];
        $ext = $f['ext'];
        $bytes = @filesize($tmp) ?: 0;
        $normalized = null;

        try {
            $speech = app(SpeechService::class);
            $text = $speech->transcribeFile($tmp, 'voice.'.$ext) ?? '';

            $normalized = app(WhatsAppAudioConverter::class)->toWhatsAppFormat($tmp);

            if ($text === '' && $bytes > 0 && $normalized && is_file($normalized)) {
                Log::channel('voice')->info('Inbound transcribe: retrying Whisper after ffmpeg normalize', [
                    'media_id' => $mediaId,
                    'converted' => basename($normalized),
                ]);
                $text = $speech->transcribeFile($normalized, basename($normalized)) ?? '';
            } elseif ($text === '' && $bytes > 0) {
                Log::channel('voice')->warning('Inbound transcribe: ffmpeg normalize skipped or failed', [
                    'media_id' => $mediaId,
                    'mime' => $f['mime'],
                ]);
            }

            $sourceForPublic = ($normalized && is_file($normalized)) ? $normalized : $tmp;
            $finalExt = strtolower(pathinfo($sourceForPublic, PATHINFO_EXTENSION) ?: $ext);
            $relative = 'whatsapp/inbound/'.Str::uuid().'.'.$finalExt;

            $raw = file_get_contents($sourceForPublic);
            if ($raw === false || $raw === '') {
                Log::channel('voice')->warning('Inbound voice: empty file after processing', ['media_id' => $mediaId]);
                @unlink($tmp);
                if ($normalized && is_file($normalized) && $normalized !== $tmp) {
                    @unlink($normalized);
                }

                return null;
            }

            Storage::disk('public')->put($relative, $raw);
            $publicUrl = URL::to(Storage::disk('public')->url($relative));

            @unlink($tmp);
            if ($normalized && is_file($normalized) && $normalized !== $tmp) {
                @unlink($normalized);
            }

            Log::channel('voice')->info('Inbound voice stored for inbox', [
                'media_id' => $mediaId,
                'relative' => $relative,
                'transcribed_chars' => strlen($text),
            ]);

            return [
                'text' => $text,
                'media_url' => $publicUrl,
                'media_type' => 'audio',
                'filename' => 'Voice note.'.$finalExt,
            ];
        } catch (\Throwable $e) {
            Log::channel('voice')->error('processInboundVoiceNote failed', [
                'media_id' => $mediaId,
                'error' => $e->getMessage(),
            ]);
            @unlink($tmp);
            if (is_string($normalized) && is_file($normalized) && $normalized !== $tmp) {
                @unlink($normalized);
            }

            return null;
        }
    }

    /**
     * Transcribe only (no disk copy) — fallback if storing the file fails.
     */
    protected function legacyTranscribeWithoutStorage(PlatformMetaConnection $platform, string $mediaId): ?string
    {
        $f = $this->fetchInboundAudioTemporaryFile($platform, $mediaId);
        if (! $f) {
            return null;
        }

        $tmp = $f['path'];
        $ext = $f['ext'];
        $bytes = @filesize($tmp) ?: 0;

        try {
            $speech = app(SpeechService::class);
            $text = $speech->transcribeFile($tmp, 'voice.'.$ext) ?? '';

            if (($text === null || $text === '') && $bytes > 0) {
                $converted = app(WhatsAppAudioConverter::class)->toWhatsAppFormat($tmp);
                if ($converted && is_file($converted)) {
                    Log::channel('voice')->info('Inbound transcribe: retrying Whisper after ffmpeg normalize', [
                        'media_id' => $mediaId,
                        'converted' => basename($converted),
                    ]);
                    $text = $speech->transcribeFile($converted, basename($converted));
                    @unlink($converted);
                } else {
                    Log::channel('voice')->warning('Inbound transcribe: ffmpeg normalize skipped or failed', [
                        'media_id' => $mediaId,
                        'mime' => $f['mime'],
                    ]);
                }
            }

            @unlink($tmp);

            return $text !== '' ? $text : null;
        } catch (\Throwable $e) {
            Log::error('Audio transcription pipeline failed', ['error' => $e->getMessage()]);
            Log::channel('voice')->error('Inbound transcribe: exception', [
                'media_id' => $mediaId,
                'error' => $e->getMessage(),
            ]);
            @unlink($tmp);

            return null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Idempotency Protection
    |--------------------------------------------------------------------------
    */
    protected function isDuplicate(string $messageId): bool
    {
        $key = "wa_msg_$messageId";

        if (Cache::has($key)) {
            return true;
        }

        Cache::put($key, true, now()->addMinutes(10));

        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Signature
    |--------------------------------------------------------------------------
    */
    protected function isValidSignature(Request $request): bool
    {
        $signature = $request->header('X-Hub-Signature-256');

        if (! $signature) {
            return false;
        }

        $appSecret = config('services.whatsapp_webhook.app_secret');

        if (! $appSecret) {
            return false;
        }

        $expected = 'sha256='.hash_hmac(
            'sha256',
            $request->getContent(),
            $appSecret
        );

        return hash_equals($expected, $signature);
    }
}
