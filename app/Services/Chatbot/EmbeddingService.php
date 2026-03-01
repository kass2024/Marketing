<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Support\Str as SupportStr;

class EmbeddingService
{
    protected string $model = 'text-embedding-3-small';
    protected int $timeout = 20;
    protected int $maxInputLength = 8000;
    protected int $cacheMinutes = 10080; // 7 days

    public function generate(string $text): ?array
    {
        $requestId = SupportStr::uuid()->toString();

        try {

            $text = trim($text);

            if ($text === '') {
                Log::warning('Embedding skipped: empty text', compact('requestId'));
                return null;
            }

            $normalized = $this->normalize($text);

            if (strlen($normalized) > $this->maxInputLength) {
                $normalized = substr($normalized, 0, $this->maxInputLength);
                Log::info('Embedding truncated due to length', compact('requestId'));
            }

            $hash = hash('sha256', $normalized);

            if ($cached = Cache::get("embedding:$hash")) {
                return $cached;
            }

            $apiKey = config('services.openai.key');

            if (!$apiKey) {
                Log::critical('OpenAI API key missing for embeddings', compact('requestId'));
                return null;
            }

            $response = Http::withHeaders([
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type'  => 'application/json',
                ])
                ->timeout($this->timeout)
                ->retry(3, 500)
                ->post('https://api.openai.com/v1/embeddings', [
                    'model' => $this->model,
                    'input' => $normalized,
                ]);

            if ($response->failed()) {

                Log::error('Embedding API failed', [
                    'request_id' => $requestId,
                    'status'     => $response->status(),
                    'body'       => $response->body(),
                ]);

                return null;
            }

            $json = $response->json();

            $embedding = Arr::get($json, 'data.0.embedding');

            if (!is_array($embedding)) {

                Log::error('Invalid embedding response structure', [
                    'request_id' => $requestId,
                    'response'   => $json,
                ]);

                return null;
            }

            Cache::put("embedding:$hash", $embedding, now()->addMinutes($this->cacheMinutes));

            return $embedding;

        } catch (\Throwable $e) {

            Log::error('Embedding exception', [
                'request_id' => $requestId,
                'error'      => $e->getMessage(),
                'file'       => $e->getFile(),
                'line'       => $e->getLine(),
            ]);

            return null;
        }
    }

    protected function normalize(string $text): string
    {
        $text = Str::lower($text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }
}