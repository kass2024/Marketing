<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Models\KnowledgeBase;
use App\Models\AiCache;
use App\Models\ConversationMemory;

class AIEngine
{
    protected float $confidenceThreshold = 0.80;

    public function reply(int $clientId, string $message, $conversation = null): string
    {
        $message = trim($message);

        if (!$message) {
            return "How can I assist you today?";
        }

        // 1️⃣ Check cache
        $hash = hash('sha256', $message);

        $cached = AiCache::where('client_id', $clientId)
            ->where('message_hash', $hash)
            ->first();

        if ($cached) {
            return $cached->response;
        }

        // 2️⃣ Semantic Search
        $semantic = $this->semanticSearch($clientId, $message);

        if ($semantic && $semantic['score'] >= $this->confidenceThreshold) {
            return $this->storeAndReturn($clientId, $hash, $semantic['answer']);
        }

        // 3️⃣ FULLTEXT fallback
        $keywordMatch = KnowledgeBase::where('client_id', $clientId)
            ->whereRaw("MATCH(question, answer) AGAINST(? IN NATURAL LANGUAGE MODE)", [$message])
            ->first();

        if ($keywordMatch) {
            return $this->storeAndReturn($clientId, $hash, $keywordMatch->answer);
        }

        // 4️⃣ OpenAI fallback with memory
        return $this->openAIFallback($clientId, $hash, $message, $conversation);
    }

    protected function semanticSearch(int $clientId, string $message): ?array
    {
        $embeddingService = app(EmbeddingService::class);
        $queryVector = $embeddingService->generate($message);

        if (!$queryVector) return null;

        $bestMatch = null;
        $bestScore = 0;

        foreach (KnowledgeBase::where('client_id', $clientId)->get() as $item) {

            if (!$item->embedding) continue;

            $storedVector = json_decode($item->embedding, true);

            $score = $this->cosineSimilarity($queryVector, $storedVector);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $item;
            }
        }

        if (!$bestMatch) return null;

        return [
            'answer' => $bestMatch->answer,
            'score' => $bestScore
        ];
    }

    protected function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0; $normA = 0; $normB = 0;

        foreach ($a as $i => $val) {
            $dot += $val * ($b[$i] ?? 0);
            $normA += $val * $val;
            $normB += ($b[$i] ?? 0) * ($b[$i] ?? 0);
        }

        return $dot / (sqrt($normA) * sqrt($normB) + 1e-10);
    }

    protected function openAIFallback(int $clientId, string $hash, string $message, $conversation)
    {
        $apiKey = config('services.openai.key');

        $memory = $conversation
            ? ConversationMemory::where('conversation_id', $conversation->id)
                ->latest()->take(5)->get()->reverse()->values()
            : collect();

        $messages = [
            ['role' => 'system', 'content' => 'You are a professional visa consultancy assistant.']
        ];

        foreach ($memory as $m) {
            $messages[] = [
                'role' => $m->role,
                'content' => $m->content
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        $response = Http::withToken($apiKey)
            ->timeout(30)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => $messages,
                'temperature' => 0.3
            ]);

        if ($response->failed()) {
            Log::error('OpenAI fallback failed', ['body'=>$response->body()]);
            return "Our team will assist you shortly.";
        }

        $answer = $response->json('choices.0.message.content');

        return $this->storeAndReturn($clientId, $hash, $answer);
    }

    protected function storeAndReturn(int $clientId, string $hash, string $answer): string
    {
        AiCache::updateOrCreate(
            ['client_id'=>$clientId,'message_hash'=>$hash],
            ['response'=>$answer]
        );

        return $answer;
    }
}