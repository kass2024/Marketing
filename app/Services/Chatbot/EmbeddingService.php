<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmbeddingService
{
    public function generate(string $text): ?array
    {
        $apiKey = config('services.openai.key');

        $response = Http::withToken($apiKey)
            ->post('https://api.openai.com/v1/embeddings', [
                'model' => 'text-embedding-3-small',
                'input' => $text,
            ]);

        if ($response->failed()) {
            Log::error('Embedding generation failed', [
                'body' => $response->body()
            ]);
            return null;
        }

        return $response->json('data.0.embedding');
    }
}