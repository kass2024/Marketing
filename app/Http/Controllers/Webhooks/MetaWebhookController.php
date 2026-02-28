<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\PlatformMetaConnection;
use App\Services\Chatbot\ChatbotProcessor;
use App\Services\Chatbot\MessageDispatcher;

class MetaWebhookController extends Controller
{
    protected ChatbotProcessor $processor;
    protected MessageDispatcher $dispatcher;

    public function __construct(
        ChatbotProcessor $processor,
        MessageDispatcher $dispatcher
    ) {
        $this->processor  = $processor;
        $this->dispatcher = $dispatcher;
    }

    /*
    |--------------------------------------------------------------------------
    | 1️⃣ Webhook Verification (Meta Setup)
    |--------------------------------------------------------------------------
    */
    public function verify(Request $request)
    {
        if (
            $request->get('hub.mode') === 'subscribe' &&
            $request->get('hub.verify_token') === config('services.whatsapp_webhook.verify_token')
        ) {
            return response($request->get('hub.challenge'), 200);
        }

        return response('Invalid verification token', 403);
    }

    /*
    |--------------------------------------------------------------------------
    | 2️⃣ Handle Incoming Events
    |--------------------------------------------------------------------------
    */
    public function handle(Request $request)
    {
        // 🔐 Signature validation
        if (!$this->isValidSignature($request)) {
            Log::warning('Invalid webhook signature.');
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $payload = $request->all();

        if (($payload['object'] ?? null) !== 'whatsapp_business_account') {
            return response()->json(['status' => 'ignored']);
        }

        foreach ($payload['entry'] ?? [] as $entry) {

            foreach ($entry['changes'] ?? [] as $change) {

                $value = $change['value'] ?? [];

                // 🔵 Handle incoming messages
                if (!empty($value['messages'])) {
                    $this->handleMessages($value);
                }

                // 🟢 Handle message status updates
                if (!empty($value['statuses'])) {
                    $this->handleStatuses($value['statuses']);
                }
            }
        }

        return response()->json(['status' => 'processed']);
    }

    /*
    |--------------------------------------------------------------------------
    | Handle Incoming Messages
    |--------------------------------------------------------------------------
    */
    protected function handleMessages(array $value): void
    {
        $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;

        if (!$phoneNumberId) {
            Log::warning('Missing phone_number_id in webhook.');
            return;
        }

        $platform = PlatformMetaConnection::where('phone_number_id', $phoneNumberId)->first();

        if (!$platform) {
            Log::warning('No platform found for phone_number_id.', [
                'phone_number_id' => $phoneNumberId
            ]);
            return;
        }

        foreach ($value['messages'] as $incoming) {

            $from       = $incoming['from'] ?? null;
            $messageId  = $incoming['id'] ?? null;
            $timestamp  = $incoming['timestamp'] ?? null;

            if (!$from || !$messageId) {
                continue;
            }

            // 🚫 Prevent duplicate processing
            if (Cache::has('wa_msg_' . $messageId)) {
                return;
            }
            Cache::put('wa_msg_' . $messageId, true, now()->addMinutes(5));

            $text = $this->extractMessageText($incoming);

            if (!$text) {
                Log::info('Unsupported message type received.', [
                    'type' => $incoming['type'] ?? 'unknown'
                ]);
                return;
            }

            try {

                $reply = $this->processor->process([
                    'from'        => $from,   // phone_number
                    'text'        => $text,
                    'platform_id' => $platform->id,
                    'message_id'  => $messageId,
                    'timestamp'   => $timestamp,
                ]);

                if ($reply) {
                    $this->dispatcher->send($from, $reply);
                }

            } catch (\Throwable $e) {

                Log::error('Webhook processing failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Handle Status Updates
    |--------------------------------------------------------------------------
    */
    protected function handleStatuses(array $statuses): void
    {
        foreach ($statuses as $status) {

            Log::info('WhatsApp Status Update', [
                'message_id' => $status['id'] ?? null,
                'status'     => $status['status'] ?? null,
                'recipient'  => $status['recipient_id'] ?? null,
            ]);

            // Optional:
            // Update message status table here if you store outgoing messages
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Extract Text From Message
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
    | Validate Meta Signature
    |--------------------------------------------------------------------------
    */
    protected function isValidSignature(Request $request): bool
    {
        $signature = $request->header('X-Hub-Signature-256');

        if (!$signature) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac(
            'sha256',
            $request->getContent(),
            config('services.whatsapp_webhook.app_secret')
        );

        return hash_equals($expected, $signature);
    }
}