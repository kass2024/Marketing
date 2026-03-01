<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EmbeddingService
{
    protected string $model = 'text-embedding-3-small';
    protected int $timeout = 30;

    public function generate(string $text): ?array
    {
        $text = trim($text);

        if (empty($text)) {
            Log::warning('Embedding skipped: empty text');
            return null;
        }

        $apiKey = config('services.openai.key');

        if (!$apiKey) {
            Log::critical('OpenAI API key missing for embeddings');
            return null;
        }

        try {

            $response = Http::withToken($apiKey)
                ->timeout($this->timeout)
                ->retry(2, 500) // retry twice with 500ms delay
                ->post('https://api.openai.com/v1/embeddings', [
                    'model' => $this->model,
                    'input' => $text
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

            return $embedding;

        } catch (\Throwable $e) {

            Log::error('Embedding exception', [
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }
}