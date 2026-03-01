<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
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
        array $payload
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
        $results  = [];

        /*
        |--------------------------------------------------------------------------
        | 1️⃣ SEND TEXT FIRST
        |--------------------------------------------------------------------------
        */
        if (!empty($payload['text'])) {
            $results[] = $this->sendText(
                $endpoint,
                $token,
                $to,
                $payload['text']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 2️⃣ SEND ATTACHMENTS
        |--------------------------------------------------------------------------
        */
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
    | SEND ATTACHMENT (ENTERPRISE SAFE)
    |--------------------------------------------------------------------------
    */
    protected function sendAttachment(
        string $endpoint,
        string $token,
        string $to,
        array $attachment
    ): array {

        $type = strtolower($attachment['type'] ?? 'document');

        // Normalize common extensions
        if (in_array($type, ['jpg','jpeg','png','gif','webp'])) {
            $type = 'image';
        }

        if ($type === 'pdf') {
            $type = 'document';
        }

        $link = $this->resolveAttachmentUrl($attachment);

        if (!$link) {
            Log::error('Attachment URL missing or invalid', $attachment);

            return [
                'success' => false,
                'error'   => 'Invalid attachment URL',
            ];
        }

        Log::info('Sending WhatsApp attachment', [
            'type' => $type,
            'link' => $link
        ]);

        switch ($type) {

            case 'image':
                return $this->post($endpoint, $token, [
                    'messaging_product' => 'whatsapp',
                    'to'   => $to,
                    'type' => 'image',
                    'image' => [
                        'link' => $link,
                    ],
                ]);

            case 'document':
                return $this->post($endpoint, $token, [
                    'messaging_product' => 'whatsapp',
                    'to'   => $to,
                    'type' => 'document',
                    'document' => [
                        'link'     => $link,
                        'filename' => $attachment['filename'] ?? basename($link),
                    ],
                ]);

            default:
                Log::warning('Unsupported attachment type', [
                    'type' => $type
                ]);

                return [
                    'success' => false,
                    'error'   => 'Unsupported attachment type',
                ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | RESOLVE ATTACHMENT URL (CRITICAL FIX)
    |--------------------------------------------------------------------------
    */
    protected function resolveAttachmentUrl(array $attachment): ?string
    {
        // If already full URL
        if (!empty($attachment['url']) &&
            filter_var($attachment['url'], FILTER_VALIDATE_URL)) {
            return $attachment['url'];
        }

        // If stored as /storage/faq_attachments/...
        if (!empty($attachment['file_path'])) {
            return URL::to('/storage/' . ltrim($attachment['file_path'], '/'));
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | CORE POST METHOD
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
                'error'       => $e->getMessage(),
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