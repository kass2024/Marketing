<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\KnowledgeBase;
use App\Models\AiCache;
use App\Models\ConversationMemory;

class AIEngine
{
    /**
     * ===========================
     * CONFIGURATION
     * ===========================
     */
    protected float $strongMatchThreshold = 0.70;
    protected float $borderlineThreshold  = 0.55;
    protected int   $candidateLimit       = 5;
    protected int   $memoryLimit          = 5;
    protected int   $timeout              = 30;

    /**
     * ===========================
     * MAIN ENTRY
     * ===========================
     */
    public function reply(int $clientId, string $message, $conversation = null): string
{
    $message = trim($message);

    if ($message === '') {
        return "How can we assist you today?";
    }

    $normalized = Str::lower($message);

    if ($this->isGreeting($normalized)) {
        return "Hello 👋 How can we assist you today regarding study or visa services?";
    }

    $hash = hash('sha256', $clientId . $normalized);

    if ($cached = AiCache::where('client_id',$clientId)
        ->where('message_hash',$hash)->first()) {
        return $cached->response;
    }

    // 🚨 NEW: Short vague query detection
    if (str_word_count($normalized) <= 3) {
        return $this->advisoryFallback($clientId,$hash,$message,$conversation);
    }

    // 🚨 NEW: Intent keyword routing
    if ($this->looksLikeGeneralAdvisory($normalized)) {
        return $this->advisoryFallback($clientId,$hash,$message,$conversation);
    }

    $candidates = $this->retrieveCandidates($clientId,$message);

    if (!empty($candidates)) {

        $best = $candidates[0];

        Log::info('Top semantic score', ['score'=>$best['score']]);

        if ($best['score'] >= 0.78) {   // 🚨 stricter threshold
            return $this->store($clientId,$hash,$best['answer']);
        }

        if ($best['score'] >= 0.65) {
            if ($reranked = $this->rerankWithAI($message,$candidates)) {
                return $this->store($clientId,$hash,$reranked);
            }
        }
    }

    return $this->advisoryFallback($clientId,$hash,$message,$conversation);
}

protected function looksLikeGeneralAdvisory(string $message): bool
{
    $keywords = [
        'work in','migrate','move to','immigrate',
        'live in','jobs in','work permit',
        'how can i go','process to go'
    ];

    foreach ($keywords as $word) {
        if (Str::contains($message,$word)) {
            return true;
        }
    }

    return false;
}
    /**
     * ===========================
     * GREETING DETECTION
     * ===========================
     */
    protected function isGreeting(string $message): bool
    {
        return in_array($message, [
            'hi', 'hello', 'hey',
            'good morning', 'good afternoon', 'good evening'
        ]);
    }

    /**
     * ===========================
     * SEMANTIC RETRIEVAL
     * ===========================
     */
    protected function retrieveCandidates(int $clientId, string $message): array
    {
        try {

            $queryVector = app(\App\Services\Chatbot\EmbeddingService::class)
                ->generate($message);

            if (!$queryVector) {
                Log::warning('Embedding generation failed');
                return [];
            }

            $items = KnowledgeBase::where('client_id', $clientId)
                ->whereNotNull('embedding')
                ->get();

            $results = [];

            foreach ($items as $item) {

                $vector = json_decode($item->embedding, true);
                if (!$vector) continue;

                $score = $this->cosineSimilarity($queryVector, $vector);

                $results[] = [
                    'question' => $item->question,
                    'answer'   => $item->answer,
                    'score'    => $score
                ];
            }

            usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

            return array_slice($results, 0, $this->candidateLimit);

        } catch (\Throwable $e) {

            Log::error('Semantic retrieval error', [
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    protected function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0; $normA = 0; $normB = 0;

        foreach ($a as $i => $v) {
            $dot   += $v * ($b[$i] ?? 0);
            $normA += $v * $v;
            $normB += ($b[$i] ?? 0) * ($b[$i] ?? 0);
        }

        return $dot / (sqrt($normA) * sqrt($normB) + 1e-10);
    }

    /**
     * ===========================
     * AI RERANK (ONLY TOP 5)
     * ===========================
     */
    protected function rerankWithAI(string $message, array $candidates): ?string
    {
        try {

            $apiKey = config('services.openai.key');
            if (!$apiKey) return null;

            $context = '';

            foreach ($candidates as $i => $c) {
                $context .= ($i + 1) . ". {$c['question']}\n";
            }

            $prompt = "User Question:\n{$message}\n\nSelect the most relevant number:\n{$context}\nOnly respond with the number.";

            $response = Http::withToken($apiKey)
                ->timeout($this->timeout)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        ['role' => 'system', 'content' => 'Select best matching question number only.'],
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'temperature' => 0
                ]);

            if ($response->failed()) {
                Log::error('Rerank API failed');
                return null;
            }

            $choice = intval(trim($response->json('choices.0.message.content'))) - 1;

            return $candidates[$choice]['answer'] ?? null;

        } catch (\Throwable $e) {

            Log::error('Rerank error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * ===========================
     * ADVISORY FALLBACK (SMART AI)
     * ===========================
     */
    protected function advisoryFallback(int $clientId, string $hash, string $message, $conversation): string
    {
        try {

            $apiKey = config('services.openai.key');
            if (!$apiKey) {
                return "Please contact our team for accurate assistance.";
            }

            $memoryContext = '';

            if ($conversation) {
                $memory = ConversationMemory::where('conversation_id', $conversation->id)
                    ->latest()
                    ->take($this->memoryLimit)
                    ->get()
                    ->reverse();

                foreach ($memory as $m) {
                    $memoryContext .= "{$m->role}: {$m->content}\n";
                }
            }

            $prompt = "
You are a professional visa and study consultancy assistant.

If the question relates to study, visa, work abroad, or immigration,
provide a helpful advisory response even if not found in the FAQ.

If the question is unrelated to visa or study services,
politely redirect to company services.

Conversation Context:
{$memoryContext}

User Question:
{$message}
";

            $response = Http::withToken($apiKey)
                ->timeout($this->timeout)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        ['role' => 'system', 'content' => 'Professional visa consultancy assistant.'],
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'temperature' => 0.3
                ]);

            if ($response->failed()) {
                Log::error('Advisory fallback API failed');
                return "Please contact our team for accurate assistance.";
            }

            $answer = trim($response->json('choices.0.message.content'));

            return $this->store($clientId, $hash, $answer);

        } catch (\Throwable $e) {

            Log::error('Fallback error', ['error' => $e->getMessage()]);
            return "Please contact our team for accurate assistance.";
        }
    }

    /**
     * ===========================
     * CACHE STORE
     * ===========================
     */
    protected function store(int $clientId, string $hash, string $answer): string
    {
        AiCache::updateOrCreate(
            ['client_id' => $clientId, 'message_hash' => $hash],
            ['response' => $answer]
        );

        return $answer;
    }
}