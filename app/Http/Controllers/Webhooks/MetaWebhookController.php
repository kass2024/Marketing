<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use App\Models\PlatformMetaConnection;
use App\Models\Client;
use App\Models\Message;
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
    | 1️⃣ Webhook Verification
    |--------------------------------------------------------------------------
    */
    public function verify(Request $request): Response
    {
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

        Log::warning('Webhook verification failed');
        return response('Forbidden', 403);
    }

    /*
    |--------------------------------------------------------------------------
    | 2️⃣ Handle Webhook
    |--------------------------------------------------------------------------
    */
    public function handle(Request $request): Response
    {
        if (!$this->isValidSignature($request)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $payload = $request->json()->all();

        if (($payload['object'] ?? null) !== 'whatsapp_business_account') {
            return response()->json(['status' => 'ignored'], 200);
        }

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {

                $value = $change['value'] ?? [];

                if (!empty($value['messages'])) {
                    $this->handleIncomingMessages($value);
                }

                if (!empty($value['statuses'])) {
                    $this->handleStatusUpdates($value['statuses']);
                }
            }
        }

        return response()->json(['status' => 'ok'], 200);
    }

    /*
    |--------------------------------------------------------------------------
    | Handle Incoming Messages
    |--------------------------------------------------------------------------
    */
    protected function handleIncomingMessages(array $value): void
    {
        $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;

        if (!$phoneNumberId) {
            Log::warning('Missing phone_number_id');
            return;
        }

        $platform = PlatformMetaConnection::where(
            'whatsapp_phone_number_id',
            $phoneNumberId
        )->first();

        if (!$platform) {
            Log::warning('Platform not found', ['phone_number_id' => $phoneNumberId]);
            return;
        }

        $clientId = $this->resolveClientId($platform);

        if (!$clientId) {
            return;
        }

        foreach ($value['messages'] as $incoming) {

            $from      = $incoming['from'] ?? null;
            $messageId = $incoming['id'] ?? null;

            if (!$from || !$messageId) {
                continue;
            }

            if ($this->isDuplicate($messageId)) {
                continue;
            }

            $text = $this->extractMessageText($incoming);

            if (!$text) {
                continue;
            }

            try {

                $aiResponse = $this->processor->process([
                    'from'       => $from,
                    'text'       => $text,
                    'client_id'  => $clientId,
                    'message_id' => $messageId,
                ]);

                if (!empty($aiResponse)) {

                    $results = $this->dispatcher->send(
                        platform: $platform,
                        to: $from,
                        payload: $aiResponse
                    );

                    $this->storeExternalIds($results);
                }

            } catch (\Throwable $e) {
                Log::error('Message processing failed', [
                    'error' => $e->getMessage(),
                    'message_id' => $messageId,
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
            $delivery   = $status['status'] ?? null;

            if (!$externalId || !$delivery) {
                continue;
            }

            Message::where('external_message_id', $externalId)
                ->update(['status' => $delivery]);

            Log::info('Message status updated', [
                'external_id' => $externalId,
                'status' => $delivery
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Store External Message IDs
    |--------------------------------------------------------------------------
    */
    protected function storeExternalIds(array $results): void
    {
        foreach ($results as $result) {

            if (!empty($result['external_message_id'])) {

                Message::whereNull('external_message_id')
                    ->latest()
                    ->limit(1)
                    ->update([
                        'external_message_id' => $result['external_message_id'],
                        'status' => 'sent'
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

        if (!$clientId) {
            Log::error('Client not found for platform', [
                'platform_id' => $platform->id
            ]);
        }

        return $clientId;
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
    | Idempotency
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

        if (!$signature) {
            return false;
        }

        $appSecret = config('services.whatsapp_webhook.app_secret');

        if (!$appSecret) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac(
            'sha256',
            $request->getContent(),
            $appSecret
        );

        return hash_equals($expected, $signature);
    }
}