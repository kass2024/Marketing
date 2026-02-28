<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Bus;
use App\Services\Chatbot\ChatbotProcessor;
use App\Models\PlatformMetaConnection;

class MetaWebhookController extends Controller
{
    protected ChatbotProcessor $processor;

    public function __construct(ChatbotProcessor $processor)
    {
        $this->processor = $processor;
    }

    /*
    |--------------------------------------------------------------------------
    | 1️⃣ Webhook Verification (Meta Setup)
    |--------------------------------------------------------------------------
    */
    public function verify(Request $request)
    {
        $verifyToken = config('services.meta.verify_token');

        if (
            $request->get('hub_mode') === 'subscribe' &&
            $request->get('hub_verify_token') === $verifyToken
        ) {
            return response($request->get('hub_challenge'), 200);
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
        // 🔐 Enterprise signature validation
        if (!$this->isValidSignature($request)) {
            Log::warning('Invalid Meta webhook signature');
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $payload = $request->all();

        Log::info('Meta Webhook Received', $payload);

        // Basic structure validation
        if (!isset($payload['entry'][0]['changes'][0]['value'])) {
            return response()->json(['status' => 'ignored']);
        }

        $value = $payload['entry'][0]['changes'][0]['value'];

        /*
        |--------------------------------------------------------------------------
        | A️⃣ MESSAGE EVENTS
        |--------------------------------------------------------------------------
        */
        if (isset($value['messages'][0])) {

            $incoming = $value['messages'][0];

            $from = $incoming['from'] ?? null;
            $type = $incoming['type'] ?? null;

            if (!$from) {
                return response()->json(['status' => 'invalid_sender']);
            }

            $text = $this->extractMessageText($incoming);

            if (!$text) {
                return response()->json(['status' => 'unsupported_type']);
            }

            // Ensure platform is connected
            if (!PlatformMetaConnection::exists()) {
                Log::warning('No platform connected but message received.');
                return response()->json(['status' => 'no_platform']);
            }

            // Queue processing (scalable)
            dispatch(function () use ($from, $text) {
                $this->processor->process([
                    'from' => $from,
                    'text' => $text,
                ]);
            });

            return response()->json(['status' => 'processed']);
        }

        /*
        |--------------------------------------------------------------------------
        | B️⃣ STATUS UPDATES (Delivered, Read, Failed)
        |--------------------------------------------------------------------------
        */
        if (isset($value['statuses'])) {
            Log::info('Meta Status Update', $value['statuses']);
            return response()->json(['status' => 'status_received']);
        }

        return response()->json(['status' => 'ignored']);
    }

    /*
    |--------------------------------------------------------------------------
    | Extract message content (supports multiple types)
    |--------------------------------------------------------------------------
    */
    protected function extractMessageText(array $incoming): ?string
    {
        return match ($incoming['type'] ?? null) {
            'text'      => $incoming['text']['body'] ?? null,
            'button'    => $incoming['button']['text'] ?? null,
            'interactive' => $incoming['interactive']['button_reply']['title']
                                ?? $incoming['interactive']['list_reply']['title']
                                ?? null,
            default     => null,
        };
    }

    /*
    |--------------------------------------------------------------------------
    | 🔐 Validate Webhook Signature
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
            config('services.meta.app_secret')
        );

        return hash_equals($expected, $signature);
    }
}