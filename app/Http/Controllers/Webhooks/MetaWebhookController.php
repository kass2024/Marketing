<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use App\Models\PlatformMetaConnection;
use App\Services\Chatbot\ChatbotProcessor;
use App\Services\Chatbot\MessageDispatcher;

class MetaWebhookController extends Controller
{
    public function __construct(
        protected ChatbotProcessor $processor,
        protected MessageDispatcher $dispatcher
    ) {}

    /*
    |--------------------------------------------------------------------------
    | 1️⃣ Webhook Verification (Meta Setup)
    |--------------------------------------------------------------------------
    */
public function verify(Request $request): Response
{
    // Apache converts dots to underscores
    $mode      = $request->input('hub_mode') ?? $request->input('hub.mode');
    $token     = $request->input('hub_verify_token') ?? $request->input('hub.verify_token');
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

    Log::warning('Webhook verification failed.', [
        'mode' => $mode,
        'token_received' => $token,
    ]);

    return response('Forbidden', 403);
}

    /*
    |--------------------------------------------------------------------------
    | 2️⃣ Handle Incoming Webhook
    |--------------------------------------------------------------------------
    */
    public function handle(Request $request): Response
    {
        //Validate signature (CRITICAL in production)
        if (!$this->isValidSignature($request)) {
            Log::error('Webhook signature validation failed.');
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $payload = $request->json()->all();

        if (!isset($payload['object']) ||
            $payload['object'] !== 'whatsapp_business_account'
        ) {
            return response()->json(['status' => 'ignored'], 200);
        }

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {

                $value = $change['value'] ?? [];

                if (!empty($value['messages'])) {
                    $this->processMessages($value);
                }

                if (!empty($value['statuses'])) {
                    $this->processStatuses($value['statuses']);
                }
            }
        }

        return response()->json(['status' => 'ok'], 200);
    }

    /*
    |--------------------------------------------------------------------------
    | Process Incoming Messages
    |--------------------------------------------------------------------------
    */
    protected function processMessages(array $value): void
    {
        $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;

        if (!$phoneNumberId) {
            Log::warning('Webhook missing phone_number_id.');
            return;
        }

        $platform = PlatformMetaConnection::where(
            'whatsapp_phone_number_id',
            $phoneNumberId
        )->first();

        if (!$platform) {
            Log::warning('No platform found for phone_number_id.', [
                'phone_number_id' => $phoneNumberId
            ]);
            return;
        }

        foreach ($value['messages'] as $incoming) {

            $from      = $incoming['from'] ?? null;
            $messageId = $incoming['id'] ?? null;

            if (!$from || !$messageId) {
                continue;
            }

            // Idempotency protection (prevent duplicate processing)
            $cacheKey = "wa_msg_$messageId";

            if (Cache::has($cacheKey)) {
                continue;
            }

            Cache::put($cacheKey, true, now()->addMinutes(10));

            $text = $this->extractMessageText($incoming);

            if (!$text) {
                Log::info('Unsupported message type received.', [
                    'type' => $incoming['type'] ?? 'unknown'
                ]);
                continue;
            }

            try {
                $reply = $this->processor->process([
                    'from'        => $from,
                    'text'        => $text,
                    'platform_id' => $platform->id,
                ]);

                if (!empty($reply)) {
                    $this->dispatcher->send(
                        platform: $platform,
                        to: $from,
                        message: $reply
                    );
                }

            } catch (\Throwable $e) {
                Log::error('Webhook message processing error.', [
                    'error' => $e->getMessage(),
                    'message_id' => $messageId,
                ]);
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Process Status Updates
    |--------------------------------------------------------------------------
    */
    protected function processStatuses(array $statuses): void
    {
        foreach ($statuses as $status) {
            Log::info('WhatsApp message status update.', [
                'message_id' => $status['id'] ?? null,
                'status'     => $status['status'] ?? null,
                'recipient'  => $status['recipient_id'] ?? null,
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Extract Message Text
    |--------------------------------------------------------------------------
    */
    protected function extractMessageText(array $incoming): ?string
    {
        return match ($incoming['type'] ?? null) {

            'text' =>
                trim($incoming['text']['body'] ?? ''),

            'button' =>
                trim($incoming['button']['text'] ?? ''),

            'interactive' =>
                trim(
                    $incoming['interactive']['button_reply']['title']
                    ?? $incoming['interactive']['list_reply']['title']
                    ?? ''
                ),

            default => null,
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Webhook Signature
    |--------------------------------------------------------------------------
    */
    protected function isValidSignature(Request $request): bool
    {
        $signature = $request->header(
            config('services.whatsapp_webhook.signature_header', 'X-Hub-Signature-256')
        );

        if (!$signature) {
            Log::error('Missing signature header.');
            return false;
        }

        $appSecret = config('services.whatsapp_webhook.app_secret');

        if (!$appSecret) {
            Log::critical('WhatsApp app_secret not configured.');
            return false;
        }

        $expected = 'sha256=' . hash_hmac(
            config('services.whatsapp_webhook.hash_algo', 'sha256'),
            $request->getContent(),
            $appSecret
        );

        if (!hash_equals($expected, $signature)) {
            Log::error('Signature mismatch.', [
                'expected' => $expected,
                'received' => $signature,
            ]);
            return false;
        }

        return true;
    }
}