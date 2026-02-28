<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
    | 2️⃣ Handle Incoming Webhook Events
    |--------------------------------------------------------------------------
    */
    public function handle(Request $request)
    {
        // 🔐 Validate Signature
        if (!$this->isValidSignature($request)) {
            Log::warning('Webhook signature validation failed.');
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $payload = $request->all();

        if (!isset($payload['entry'])) {
            return response()->json(['status' => 'ignored']);
        }

        foreach ($payload['entry'] as $entry) {

            foreach ($entry['changes'] ?? [] as $change) {

                $value = $change['value'] ?? null;

                if (!$value) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | A️⃣ Incoming Messages
                |--------------------------------------------------------------------------
                */
                if (!empty($value['messages'])) {

                    foreach ($value['messages'] as $incoming) {

                        $from = $incoming['from'] ?? null;
                        $messageId = $incoming['id'] ?? null;
                        $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;

                        if (!$from || !$messageId) {
                            continue;
                        }

                        // Extract message text
                        $text = $this->extractMessageText($incoming);

                        if (!$text) {
                            Log::info('Unsupported message type received.', [
                                'type' => $incoming['type'] ?? null,
                            ]);
                            continue;
                        }

                        // Find platform connection by phone_number_id
                        $platform = PlatformMetaConnection::where(
                            'phone_number_id',
                            $phoneNumberId
                        )->first();

                        if (!$platform) {
                            Log::warning('Message received but no platform connected.', [
                                'phone_number_id' => $phoneNumberId,
                            ]);
                            continue;
                        }

                        try {

                            // 🧠 Process chatbot logic
                            $reply = $this->processor->process([
                                'from' => $from,                 // WhatsApp user number
                                'text' => $text,
                                'platform_id' => $platform->id,
                                'message_id' => $messageId,
                            ]);

                            // 🚀 Send reply if exists
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

                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | B️⃣ Status Updates (Delivered / Read / Failed)
                |--------------------------------------------------------------------------
                */
                if (!empty($value['statuses'])) {

                    foreach ($value['statuses'] as $status) {

                        Log::info('Message Status Update', [
                            'message_id' => $status['id'] ?? null,
                            'status'     => $status['status'] ?? null,
                            'recipient'  => $status['recipient_id'] ?? null,
                        ]);

                        // You can update message table here if needed
                    }

                    continue;
                }
            }
        }

        return response()->json(['status' => 'processed']);
    }

    /*
    |--------------------------------------------------------------------------
    | Extract message content
    |--------------------------------------------------------------------------
    */
    protected function extractMessageText(array $incoming): ?string
    {
        return match ($incoming['type'] ?? null) {

            'text' =>
                $incoming['text']['body'] ?? null,

            'button' =>
                $incoming['button']['text'] ?? null,

            'interactive' =>
                $incoming['interactive']['button_reply']['title']
                ?? $incoming['interactive']['list_reply']['title']
                ?? null,

            default => null,
        };
    }

    /*
    |--------------------------------------------------------------------------
    | 🔐 Validate Webhook Signature (Meta Security)
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