<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\PlatformMetaConnection;

class MessageDispatcher
{
    /**
     * Send WhatsApp text message via Meta Graph API
     */
    public function send(
        PlatformMetaConnection $platform,
        string $to,
        string $message
    ): void
    {
        Log::info('WhatsApp Dispatcher started', [
            'to' => $to,
            'platform_id' => $platform->id,
        ]);

        // Validate phone number ID
        if (empty($platform->whatsapp_phone_number_id)) {
            Log::error('Missing WhatsApp phone_number_id', [
                'platform_id' => $platform->id,
            ]);
            return;
        }

        // Decrypt access token
        try {
            $token = decrypt($platform->access_token);
        } catch (\Throwable $e) {
            Log::critical('Access token decryption failed', [
                'platform_id' => $platform->id,
                'error' => $e->getMessage(),
            ]);
            return;
        }

        $graphUrl     = rtrim(config('services.meta.graph_url'), '/');
        $graphVersion = config('services.meta.graph_version');

        $endpoint = "{$graphUrl}/{$graphVersion}/{$platform->whatsapp_phone_number_id}/messages";

        try {
            $response = Http::withToken($token)
                ->timeout(15)
                ->retry(2, 500)
                ->post($endpoint, [
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                    'type' => 'text',
                    'text' => [
                        'body' => $message,
                    ],
                ]);

            if ($response->failed()) {
                Log::error('WhatsApp API request failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'platform_id' => $platform->id,
                ]);
                return;
            }

            Log::info('WhatsApp message sent successfully', [
                'to' => $to,
                'response' => $response->json(),
            ]);

        } catch (\Throwable $e) {
            Log::critical('WhatsApp API exception', [
                'error' => $e->getMessage(),
                'platform_id' => $platform->id,
            ]);
        }
    }
}