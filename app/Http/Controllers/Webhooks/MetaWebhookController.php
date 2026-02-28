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

        Log::info('Meta Webhook Received', [
            'object' => $payload['object'] ?? null,
        ]);

        if (!isset($payload['entry'][0]['changes'][0]['value'])) {
            return response()->json(['status' => 'ignored']);
        }

        $value = $payload['entry'][0]['changes'][0]['value'];

        /*
        |--------------------------------------------------------------------------
        | A️⃣ Incoming Messages
        |--------------------------------------------------------------------------
        */
        if (!empty($value['messages'][0])) {

            $incoming = $value['messages'][0];

            $from = $incoming['from'] ?? null;

            if (!$from) {
                return response()->json(['status' => 'invalid_sender']);
            }

            $text = $this->extractMessageText($incoming);

            if (!$text) {
                return response()->json(['status' => 'unsupported_type']);
            }

            // Ensure platform connection exists
            $platform = PlatformMetaConnection::first();

            if (!$platform) {
                Log::warning('Message received but no platform connected.');
                return response()->json(['status' => 'no_platform']);
            }

            try {
                // 🧠 Process chatbot logic
                $reply = $this->processor->process([
                    'from' => $from,
                    'text' => $text,
                ]);

                // 🚀 Send reply if exists
                if ($reply) {
                    $this->dispatcher->send($from, $reply);
                }

            } catch (\Throwable $e) {
                Log::error('Webhook processing failed', [
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json(['status' => 'processed']);
        }

        /*
        |--------------------------------------------------------------------------
        | B️⃣ Status Updates (Delivered / Read / Failed)
        |--------------------------------------------------------------------------
        */
        if (!empty($value['statuses'])) {

            Log::info('Message Status Update', [
                'statuses' => $value['statuses'],
            ]);

            return response()->json(['status' => 'status_received']);
        }

        return response()->json(['status' => 'ignored']);
    }

    /*
    |--------------------------------------------------------------------------
    | Extract message content
    |--------------------------------------------------------------------------
    */
    protected function extractMessageText(array $incoming): ?string
    {
        return match ($incoming['type'] ?? null) {
            'text' => $incoming['text']['body'] ?? null,

            'button' => $incoming['button']['text'] ?? null,

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