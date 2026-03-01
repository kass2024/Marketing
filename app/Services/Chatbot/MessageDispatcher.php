<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\PlatformMetaConnection;

class MessageDispatcher
{
    protected int $timeout = 20;

    /*
    |--------------------------------------------------------------------------
    | PUBLIC SEND METHOD (TEXT + ATTACHMENTS)
    |--------------------------------------------------------------------------
    */
    public function send(
        PlatformMetaConnection $platform,
        string $to,
        array $payload // structured AI response
    ): array {

        Log::info('WhatsApp Dispatcher started', [
            'to' => $to,
            'platform_id' => $platform->id,
        ]);

        if (empty($platform->whatsapp_phone_number_id)) {
            return $this->error('Missing WhatsApp phone_number_id', $platform);
        }

        $token = $this->decryptToken($platform);
        if (!$token) {
            return $this->error('Token decryption failed', $platform);
        }

        $endpoint = $this->buildEndpoint($platform);

        $results = [];

        // 1️⃣ Send Text First
        if (!empty($payload['text'])) {
            $results[] = $this->sendText(
                $endpoint,
                $token,
                $to,
                $payload['text']
            );
        }

        // 2️⃣ Send Attachments (PDF / Image / etc)
        foreach ($payload['attachments'] ?? [] as $attachment) {
            $results[] = $this->sendAttachment(
                $endpoint,
                $token,
                $to,
                $attachment
            );
        }

        return $results;
    }

    /*
    |--------------------------------------------------------------------------
    | SEND TEXT
    |--------------------------------------------------------------------------
    */
    protected function sendText(
        string $endpoint,
        string $token,
        string $to,
        string $message
    ): array {

        return $this->post($endpoint, $token, [
            'messaging_product' => 'whatsapp',
            'to'   => $to,
            'type' => 'text',
            'text' => ['body' => $message],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SEND ATTACHMENT
    |--------------------------------------------------------------------------
    */
    protected function sendAttachment(
        string $endpoint,
        string $token,
        string $to,
        array $attachment
    ): array {

        $type = $attachment['type'] ?? 'document';

        switch ($type) {

            case 'pdf':
            case 'document':
                return $this->post($endpoint, $token, [
                    'messaging_product' => 'whatsapp',
                    'to'   => $to,
                    'type' => 'document',
                    'document' => [
                        'link' => $attachment['url'] ?? asset($attachment['file_path']),
                        'filename' => $attachment['filename'] ?? 'document.pdf',
                    ],
                ]);

            case 'image':
                return $this->post($endpoint, $token, [
                    'messaging_product' => 'whatsapp',
                    'to'   => $to,
                    'type' => 'image',
                    'image' => [
                        'link' => $attachment['url'] ?? asset($attachment['file_path']),
                    ],
                ]);

            default:
                return [
                    'success' => false,
                    'error'   => 'Unsupported attachment type',
                ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CORE POST METHOD (Centralized API Call)
    |--------------------------------------------------------------------------
    */
    protected function post(string $endpoint, string $token, array $body): array
    {
        try {

            $response = Http::withToken($token)
                ->timeout($this->timeout)
                ->retry(2, 500)
                ->post($endpoint, $body);

            if ($response->failed()) {

                Log::error('WhatsApp API request failed', [
                    'status'   => $response->status(),
                    'response' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'status'  => $response->status(),
                    'error'   => $response->body(),
                ];
            }

            $data = $response->json();

            Log::info('WhatsApp message sent', [
                'response' => $data,
            ]);

            return [
                'success' => true,
                'external_message_id' =>
                    $data['messages'][0]['id'] ?? null,
                'response' => $data,
            ];

        } catch (\Throwable $e) {

            Log::critical('WhatsApp API exception', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */
    protected function decryptToken(PlatformMetaConnection $platform): ?string
    {
        try {
            return decrypt($platform->access_token);
        } catch (\Throwable $e) {
            Log::critical('Access token decryption failed', [
                'platform_id' => $platform->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    protected function buildEndpoint(PlatformMetaConnection $platform): string
    {
        $graphUrl     = rtrim(config('services.meta.graph_url'), '/');
        $graphVersion = config('services.meta.graph_version');

        return "{$graphUrl}/{$graphVersion}/{$platform->whatsapp_phone_number_id}/messages";
    }

    protected function error(string $message, PlatformMetaConnection $platform): array
    {
        Log::error($message, ['platform_id' => $platform->id]);

        return [
            'success' => false,
            'error'   => $message,
        ];
    }
}