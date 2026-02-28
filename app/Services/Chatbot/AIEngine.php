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
    protected float $semanticThreshold = 0.87;
    protected float $reRankThreshold   = 0.75;
    protected int   $candidateLimit    = 5;

    public function reply(int $clientId, string $message, $conversation = null): string
    {
        $message = $this->normalize($message);

        if (!$message) {
            return "How can we assist you today?";
        }

        $hash = hash('sha256', $clientId . $message);

        // 1️⃣ Cache
        if ($cached = AiCache::where('client_id', $clientId)
            ->where('message_hash', $hash)
            ->first()) {
            return $cached->response;
        }

        // 2️⃣ Semantic candidate search
        $candidates = $this->semanticCandidates($clientId, $message);

        if (!empty($candidates)) {

            $best = $candidates[0];

            Log::info('Semantic best score', ['score' => $best['score']]);

            if ($best['score'] >= $this->semanticThreshold) {
                return $this->store($clientId, $hash, $best['answer']);
            }

            // 3️⃣ AI re-ranking (prevents wrong answers like SEVIS example)
            $reRanked = $this->reRankWithAI($message, $candidates);

            if ($reRanked && $reRanked['confidence'] >= $this->reRankThreshold) {
                return $this->store($clientId, $hash, $reRanked['answer']);
            }
        }

        // 4️⃣ Strict grounded AI fallback
        return $this->groundedFallback($clientId, $hash, $message, $conversation);
    }

    protected function normalize(string $text): string
    {
        return trim(Str::lower($text));
    }

    /*
    |--------------------------------------------------------------------------
    | Semantic Candidate Retrieval
    |--------------------------------------------------------------------------
    */

    protected function semanticCandidates(int $clientId, string $message): array
    {
        try {

            $queryVector = app(EmbeddingService::class)->generate($message);

            if (!$queryVector) return [];

            $items = KnowledgeBase::where('client_id', $clientId)
                ->whereNotNull('embedding')
                ->get();

            $results = [];

            foreach ($items as $item) {

                $vector = is_array($item->embedding)
                    ? $item->embedding
                    : json_decode($item->embedding, true);

                if (!$vector) continue;

                $score = $this->cosine($queryVector, $vector);

                $results[] = [
                    'answer' => $item->answer,
                    'question' => $item->question,
                    'score' => $score
                ];
            }

            usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

            return array_slice($results, 0, $this->candidateLimit);

        } catch (\Throwable $e) {

            Log::error('Semantic search failed', [
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    protected function cosine(array $a, array $b): float
    {
        $dot = 0; $normA = 0; $normB = 0;

        foreach ($a as $i => $v) {
            $dot += $v * ($b[$i] ?? 0);
            $normA += $v * $v;
            $normB += ($b[$i] ?? 0) * ($b[$i] ?? 0);
        }

        return $dot / (sqrt($normA) * sqrt($normB) + 1e-10);
    }

    /*
    |--------------------------------------------------------------------------
    | AI Re-ranking (Prevents wrong matches)
    |--------------------------------------------------------------------------
    */

    protected function reRankWithAI(string $message, array $candidates): ?array
    {
        try {

            $apiKey = config('services.openai.key');
            if (!$apiKey) return null;

            $context = "";

            foreach ($candidates as $i => $c) {
                $context .= "Candidate " . ($i + 1) . ":\n";
                $context .= "Question: {$c['question']}\n";
                $context .= "Answer: {$c['answer']}\n\n";
            }

            $prompt = "
You are evaluating which answer best matches the user question.

User Question:
{$message}

Below are candidate Q&A pairs.

{$context}

Return ONLY JSON like:
{
  \"best_index\": number,
  \"confidence\": decimal between 0 and 1
}
";

            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        ['role' => 'system', 'content' => 'You evaluate answer relevance. Return JSON only.'],
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'temperature' => 0
                ]);

            if ($response->failed()) {
                return null;
            }

            $content = $response->json('choices.0.message.content');

            $data = json_decode($content, true);

            if (!isset($data['best_index'])) return null;

            $index = $data['best_index'] - 1;

            if (!isset($candidates[$index])) return null;

            return [
                'answer' => $candidates[$index]['answer'],
                'confidence' => $data['confidence'] ?? 0
            ];

        } catch (\Throwable $e) {

            Log::error('Re-rank failed', [
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Strict Grounded Fallback (NO hallucination)
    |--------------------------------------------------------------------------
    */

    protected function groundedFallback(int $clientId, string $hash, string $message, $conversation): string
    {
        try {

            $apiKey = config('services.openai.key');
            if (!$apiKey) {
                return "Our team will assist you shortly.";
            }

            $knowledge = KnowledgeBase::where('client_id', $clientId)
                ->limit(50)
                ->get(['question', 'answer']);

            $kbText = "";

            foreach ($knowledge as $item) {
                $kbText .= "Q: {$item->question}\nA: {$item->answer}\n\n";
            }

            $prompt = "
You are a professional visa consultancy assistant.

You MUST answer strictly using the company knowledge below.
If the answer is not found in the knowledge, respond:

\"Please contact our team for accurate assistance.\"

Company Knowledge:
{$kbText}

User Question:
{$message}
";

            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        ['role' => 'system', 'content' => 'Strictly grounded assistant.'],
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'temperature' => 0.1,
                    'max_tokens' => 500
                ]);

            if ($response->failed()) {
                return "Our team will assist you shortly.";
            }

            $answer = trim($response->json('choices.0.message.content'));

            return $this->store($clientId, $hash, $answer);

        } catch (\Throwable $e) {

            Log::error('Fallback failed', [
                'error' => $e->getMessage()
            ]);

            return "Our team will assist you shortly.";
        }
    }

    protected function store(int $clientId, string $hash, string $answer): string
    {
        AiCache::updateOrCreate(
            [
                'client_id' => $clientId,
                'message_hash' => $hash
            ],
            [
                'response' => $answer
            ]
        );

        return $answer;
    }
}
//end of file 