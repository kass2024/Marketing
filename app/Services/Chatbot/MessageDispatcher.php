<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\PlatformMetaConnection;

class MessageDispatcher
{
    public function send(string $to, string $message): void
    {
        $platform = PlatformMetaConnection::first();

        if (!$platform) {
            Log::error('No platform connection found.');
            return;
        }

        $token = decrypt($platform->access_token);

        $response = Http::withToken($token)
            ->post(
                config('services.meta.graph_url') . '/' .
                config('services.meta.graph_version') . '/' .
                $platform->whatsapp_phone_number_id . '/messages',
                [
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                    'type' => 'text',
                    'text' => [
                        'body' => $message
                    ],
                ]
            );

        if (!$response->successful()) {
            Log::error('WhatsApp send failed', [
                'response' => $response->body()
            ]);
        }
    }
}