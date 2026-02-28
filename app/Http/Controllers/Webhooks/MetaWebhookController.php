<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\ChatbotEngineService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MetaWebhookController extends Controller
{
    protected ChatbotEngineService $engine;

    public function __construct(ChatbotEngineService $engine)
    {
        $this->engine = $engine;
    }

    /*
    |--------------------------------------------------------------------------
    | 1️⃣ Webhook Verification (Meta Setup)
    |--------------------------------------------------------------------------
    */
    public function verify(Request $request)
    {
        $verifyToken = config('services.whatsapp_webhook.verify_token');

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
        // 🔐 Validate Meta signature (Enterprise Security)
        if (!$this->isValidSignature($request)) {
            Log::warning('Invalid Meta webhook signature');
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $payload = $request->all();

        Log::info('Meta Webhook Received', $payload);

        // Validate structure safely
        if (
            !isset($payload['entry'][0]['changes'][0]['value'])
        ) {
            return response()->json(['status' => 'ignored']);
        }

        $value = $payload['entry'][0]['changes'][0]['value'];

        // Only process actual incoming messages
        if (!isset($value['messages'][0])) {
            return response()->json(['status' => 'no_message']);
        }

        $incoming = $value['messages'][0];

        $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;
        $from          = $incoming['from'] ?? null;
        $messageType   = $incoming['type'] ?? null;

        if (!$phoneNumberId || !$from) {
            return response()->json(['status' => 'invalid_payload']);
        }

        // Extract message content safely
        $text = null;

        if ($messageType === 'text') {
            $text = $incoming['text']['body'] ?? null;
        }

        if (!$text) {
            return response()->json(['status' => 'unsupported_type']);
        }

        /*
        |--------------------------------------------------------------------------
        | Multi-Tenant: Identify Client by phone_number_id
        |--------------------------------------------------------------------------
        */

        $client = Client::whereHas('metaConnection', function ($query) use ($phoneNumberId) {
            $query->where('phone_number_id', $phoneNumberId);
        })->first();

        if (!$client) {
            Log::warning("Client not found for phone_number_id: {$phoneNumberId}");
            return response()->json(['status' => 'client_not_found']);
        }

        /*
        |--------------------------------------------------------------------------
        | Run Chatbot Engine (Hybrid Bot System)
        |--------------------------------------------------------------------------
        */

        $this->engine->handleIncoming(
            phone: $from,
            message: $text,
            clientId: $client->id
        );

        return response()->json(['status' => 'processed'], 200);
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
            config('services.whatsapp_webhook.app_secret')
        );

        return hash_equals($expected, $signature);
    }
}