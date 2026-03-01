<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;

class EmbeddingService
{
    protected string $model;
    protected int $timeout = 20;
    protected int $maxInputLength = 8000;
    protected int $cacheMinutes = 10080; // 7 days

    public function __construct()
    {
        $this->model = config('services.openai.embedding_model', 'text-embedding-3-small');
    }

    /*
    |--------------------------------------------------------------------------
    | GENERATE EMBEDDING
    |--------------------------------------------------------------------------
    */

    public function generate(string $text): ?array
    {
        $requestId = Str::uuid()->toString();

        try {

            $text = trim($text);

            if ($text === '') {
                Log::warning('Embedding skipped: empty input', compact('requestId'));
                return null;
            }

            $normalized = $this->normalize($text);

            if (strlen($normalized) > $this->maxInputLength) {
                $normalized = substr($normalized, 0, $this->maxInputLength);
                Log::info('Embedding input truncated', compact('requestId'));
            }

            $hash = hash('sha256', $normalized);

            // ---------------- CACHE ----------------
            if ($cached = Cache::get("embedding:$hash")) {

                if (is_array($cached) && count($cached) > 10) {
                    return $cached;
                }

                Log::warning('Corrupted embedding cache detected', compact('requestId'));
            }

            $apiKey = config('services.openai.key');

            if (!$apiKey) {
                Log::critical('Missing OpenAI API key for embedding', compact('requestId'));
                return null;
            }

            // ---------------- API CALL ----------------
            $response = Http::withToken($apiKey)
                ->timeout($this->timeout)
                ->retry(3, 500)
                ->post('https://api.openai.com/v1/embeddings', [
                    'model' => $this->model,
                    'input' => $normalized,
                ]);

            if ($response->failed()) {

                Log::error('Embedding API request failed', [
                    'request_id' => $requestId,
                    'status'     => $response->status(),
                    'body'       => $response->body(),
                ]);

                return null;
            }

            $json = $response->json();

            $embedding = Arr::get($json, 'data.0.embedding');

            if (!is_array($embedding) || count($embedding) < 10) {

                Log::error('Invalid embedding structure returned', [
                    'request_id' => $requestId,
                    'response'   => $json,
                ]);

                return null;
            }

            // ---------------- NORMALIZE FLOATS ----------------
            $embedding = array_map(function ($value) {
                return (float) $value;
            }, $embedding);

            // ---------------- STORE CACHE ----------------
            Cache::put(
                "embedding:$hash",
                $embedding,
                now()->addMinutes($this->cacheMinutes)
            );

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

    /*
    |--------------------------------------------------------------------------
    | NORMALIZATION
    |--------------------------------------------------------------------------
    */

    protected function normalize(string $text): string
    {
        $text = Str::lower($text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = preg_replace('/[^\p{L}\p{N}\s\@\.\-]/u', '', $text);
        return trim($text);
    }
}