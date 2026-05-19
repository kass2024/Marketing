<?php

namespace App\Services\Chatbot;

use App\Models\PlatformMetaConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class MessageDispatcher
{
    protected int $timeout = 20;

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

        $tokenCandidates = $this->accessTokenCandidates($platform);
        if ($tokenCandidates === []) {
            return $this->error('No WhatsApp access token (check .env WHATSAPP_ACCESS_TOKEN or reconnect Meta)', $platform);
        }

        $endpoint = $this->buildEndpoint($platform);
        $results  = [];

        if (! empty($payload['text'])) {
            $results[] = $this->postWithTokenFallback($endpoint, $tokenCandidates, [
                'messaging_product' => 'whatsapp',
                'to'   => $to,
                'type' => 'text',
                'text' => ['body' => $payload['text']],
            ]);
        }

        foreach ($payload['attachments'] ?? [] as $attachment) {
            $results[] = $this->sendAttachment(
                $endpoint,
                $tokenCandidates,
                $to,
                $attachment
            );
        }

        if (! empty($payload['voice_url']) && filter_var($payload['voice_url'], FILTER_VALIDATE_URL)) {
            $results[] = $this->postWithTokenFallback($endpoint, $tokenCandidates, [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'audio',
                'audio' => [
                    'link' => $payload['voice_url'],
                ],
            ]);
        }

        return $results;
    }

    public function accessTokenForPlatform(PlatformMetaConnection $platform): ?string
    {
        $candidates = $this->accessTokenCandidates($platform);

        return $candidates[0]['token'] ?? null;
    }

    protected function sendAttachment(
        string $endpoint,
        array $tokenCandidates,
        string $to,
        array $attachment
    ): array {

        $type = strtolower($attachment['type'] ?? 'document');

        if (in_array($type, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            $type = 'image';
        }

        if ($type === 'pdf') {
            $type = 'document';
        }

        $link = $this->resolveAttachmentUrl($attachment);

        if (! $link) {
            Log::error('Attachment URL missing or invalid', $attachment);

            return [
                'success' => false,
                'error'   => 'Invalid attachment URL',
            ];
        }

        Log::info('Sending WhatsApp attachment', [
            'type' => $type,
            'link' => $link,
        ]);

        switch ($type) {

            case 'image':
                return $this->postWithTokenFallback($endpoint, $tokenCandidates, [
                    'messaging_product' => 'whatsapp',
                    'to'   => $to,
                    'type' => 'image',
                    'image' => [
                        'link' => $link,
                    ],
                ]);

            case 'document':
                return $this->postWithTokenFallback($endpoint, $tokenCandidates, [
                    'messaging_product' => 'whatsapp',
                    'to'   => $to,
                    'type' => 'document',
                    'document' => [
                        'link'     => $link,
                        'filename' => $attachment['filename'] ?? basename($link),
                    ],
                ]);

            case 'audio':
            case 'voice':
                return $this->postWithTokenFallback($endpoint, $tokenCandidates, [
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                    'type' => 'audio',
                    'audio' => [
                        'link' => $link,
                    ],
                ]);

            default:
                Log::warning('Unsupported attachment type', [
                    'type' => $type,
                ]);

                return [
                    'success' => false,
                    'error'   => 'Unsupported attachment type',
                ];
        }
    }

    protected function resolveAttachmentUrl(array $attachment): ?string
    {
        if (! empty($attachment['url']) &&
            filter_var($attachment['url'], FILTER_VALIDATE_URL)) {
            return $attachment['url'];
        }

        if (! empty($attachment['file_path'])) {
            return URL::to('/storage/'.ltrim($attachment['file_path'], '/'));
        }

        return null;
    }

    /**
     * @param  array<int, array{source: string, token: string}>  $tokenCandidates
     */
    protected function postWithTokenFallback(string $endpoint, array $tokenCandidates, array $body): array
    {
        $last = [
            'success' => false,
            'error'   => 'No token attempted',
        ];

        foreach ($tokenCandidates as $candidate) {
            $last = $this->post($endpoint, $candidate['token'], $body, $candidate['source']);

            if ($last['success']) {
                return $last;
            }

            if (! $this->isAuthFailure($last)) {
                return $last;
            }

            Log::warning('WhatsApp token rejected, trying next source', [
                'source' => $candidate['source'],
                'status' => $last['status'] ?? null,
            ]);
        }

        return $last;
    }

    protected function post(string $endpoint, string $token, array $body, string $source = 'unknown'): array
    {
        try {
            $response = Http::withToken($token)
                ->timeout($this->timeout)
                ->retry(2, 500, throw: false)
                ->post($endpoint, $body);

            if ($response->failed()) {
                Log::error('WhatsApp API request failed', [
                    'token_source' => $source,
                    'status'       => $response->status(),
                    'response'     => $response->body(),
                ]);

                return [
                    'success' => false,
                    'status'  => $response->status(),
                    'error'   => $response->body(),
                ];
            }

            $data = $response->json();

            Log::info('WhatsApp message sent', [
                'token_source' => $source,
                'response'     => $data,
            ]);

            return [
                'success' => true,
                'external_message_id' => $data['messages'][0]['id'] ?? null,
                'response' => $data,
            ];

        } catch (\Throwable $e) {
            Log::critical('WhatsApp API exception', [
                'token_source' => $source,
                'error'        => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Prefer .env token (fresh) then DB. Env is tried first when phone_number_id matches.
     *
     * @return array<int, array{source: string, token: string}>
     */
    protected function accessTokenCandidates(PlatformMetaConnection $platform): array
    {
        $seen = [];
        $candidates = [];

        $add = function (string $source, ?string $token) use (&$candidates, &$seen): void {
            $token = trim((string) $token);
            if ($token === '' || isset($seen[$token])) {
                return;
            }
            $seen[$token] = true;
            $candidates[] = ['source' => $source, 'token' => $token];
        };

        $envToken = config('services.whatsapp.access_token');
        if (filled($envToken)) {
            $add('env_whatsapp', $envToken);
        }

        $dbToken = $platform->plainAccessToken();
        if ($dbToken) {
            $add('platform_db', $dbToken);
        }

        $systemToken = config('services.meta.token');
        if (filled($systemToken)) {
            $add('env_meta_system', $systemToken);
        }

        return $candidates;
    }

    protected function isAuthFailure(array $result): bool
    {
        $status = (int) ($result['status'] ?? 0);
        if ($status === 401) {
            return true;
        }

        $error = (string) ($result['error'] ?? '');

        return str_contains($error, '"code":190')
            || str_contains($error, 'OAuthException')
            || str_contains($error, 'Authentication Error');
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
