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
        $mode      = $request->query('hub.mode');
        $token     = $request->query('hub.verify_token');
        $challenge = $request->query('hub.challenge');

        if ($mode === 'subscribe' &&
            hash_equals(config('services.whatsapp_webhook.verify_token'), $token)
        ) {
            return response($challenge, 200);
        }

        Log::warning('Webhook verification failed.');
        return response('Forbidden', 403);
    }

    /*
    |--------------------------------------------------------------------------
    | 2️⃣ Handle Incoming Webhook
    |--------------------------------------------------------------------------
    */
    public function handle(Request $request): Response
    {
        // 🔐 Validate signature
        if (!$this->isValidSignature($request)) {
            Log::warning('Invalid webhook signature.');
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
            Log::warning('Missing phone_number_id in webhook.');
            return;
        }

        $platform = PlatformMetaConnection::where(
            'whatsapp_phone_number_id',
            $phoneNumberId
        )->first();

        if (!$platform) {
            Log::warning('Platform not found.', [
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

            // Idempotency protection
            if (Cache::has("wa_msg_$messageId")) {
                return;
            }

            Cache::put("wa_msg_$messageId", true, now()->addMinutes(10));

            $text = $this->extractMessageText($incoming);

            if (!$text) {
                Log::info('Unsupported message type.', [
                    'type' => $incoming['type'] ?? 'unknown'
                ]);
                return;
            }

            try {
                $reply = $this->processor->process([
                    'from'        => $from,
                    'text'        => $text,
                    'platform_id' => $platform->id,
                ]);

                if ($reply) {
                    $this->dispatcher->send(
                        platform: $platform,
                        to: $from,
                        message: $reply
                    );
                }

            } catch (\Throwable $e) {

                Log::error('Message processing failed.', [
                    'error' => $e->getMessage(),
                    'platform_id' => $platform->id,
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

            // Optional: Update DB message record
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
    | Validate Meta Webhook Signature
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
            Log::critical('WhatsApp app_secret missing.');
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