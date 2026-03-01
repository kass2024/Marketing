<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class EmbeddingService
{
    /**
     * Production Settings
     */
    protected string $model = 'text-embedding-3-small';
    protected int $timeout = 20;
    protected int $maxInputLength = 8000; // safety trim
    protected int $cacheMinutes = 60 * 24 * 7; // 7 days cache

    /**
     * Generate embedding vector
     */
    public function generate(string $text): ?array
    {
        $text = trim($text);

        if ($text === '') {
            Log::warning('Embedding skipped: empty text');
            return null;
        }

        // 🔥 Normalize input (important for stable embeddings)
        $normalized = $this->normalize($text);

        // 🔥 Prevent very long inputs (protect cost + performance)
        if (strlen($normalized) > $this->maxInputLength) {
            $normalized = substr($normalized, 0, $this->maxInputLength);
            Log::info('Embedding truncated due to length');
        }

        // 🔥 Hash for caching
        $hash = hash('sha256', $normalized);

        // 🔥 Cache lookup (avoid repeated embedding cost)
        if ($cached = Cache::get("embedding:{$hash}")) {
            return $cached;
        }

        $apiKey = config('services.openai.key');

        if (!$apiKey) {
            Log::critical('OpenAI API key missing for embeddings');
            return null;
        }

        try {

            $response = Http::withToken($apiKey)
                ->timeout($this->timeout)
                ->retry(3, 500) // retry 3 times
                ->post('https://api.openai.com/v1/embeddings', [
                    'model' => $this->model,
                    'input' => $normalized,
                ]);

            if ($response->failed()) {

                Log::error('Embedding API failed', [
                    'status' => $response->status(),
                    'body'   => $response->body()
                ]);

                return null;
            }

            $embedding = $response->json('data.0.embedding');

            if (!is_array($embedding)) {

                Log::error('Invalid embedding response structure', [
                    'response' => $response->json()
                ]);

                return null;
            }

            // 🔥 Store in cache
            Cache::put("embedding:{$hash}", $embedding, now()->addMinutes($this->cacheMinutes));

            return $embedding;

        } catch (\Throwable $e) {

            Log::error('Embedding exception', [
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Normalize text for better embedding consistency
     */
    protected function normalize(string $text): string
    {
        $text = Str::lower($text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }
}