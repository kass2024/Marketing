<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\KnowledgeBase;
use App\Models\AiCache;

class AIEngine
{

    protected string $model;

    protected float $faqThreshold = 0.60;
    protected float $groundThreshold = 0.40;

    protected int $candidateLimit = 5;
    protected int $timeout = 30;

    protected bool $debug = true;

    public function __construct()
    {
        $this->model = config('services.openai.model', 'gpt-4.1-mini');
    }

    /*
    |--------------------------------------------------------------------------
    | MAIN ENTRY
    |--------------------------------------------------------------------------
    */

    public function reply(int $clientId, string $message, $conversation = null): array
    {

        $requestId = Str::uuid()->toString();
        $normalized = $this->normalize($message);
        $hash = hash('sha256', $clientId . $normalized);

        $this->log('MESSAGE_RECEIVED', [
            'conversation_id' => $conversation?->id,
            'message' => $normalized
        ], $requestId);

        /*
        |--------------------------------------------------------------------------
        | HUMAN MODE PROTECTION
        |--------------------------------------------------------------------------
        */

        if ($conversation && $conversation->status === 'human') {

            $this->log('AI_BLOCKED_HUMAN_ACTIVE', [
                'conversation_id' => $conversation->id
            ], $requestId);

            return [
                'text' => '',
                'attachments' => [],
                'confidence' => 0,
                'source' => 'human_active'
            ];
        }

        try {

            if ($normalized === '') {
                return $this->fallback("How can we assist you today?");
            }

            /*
            |--------------------------------------------------------------------------
            | USER REQUESTED HUMAN
            |--------------------------------------------------------------------------
            */

            if ($this->needsHuman($normalized)) {

                $this->log('USER_REQUESTED_AGENT', [
                    'conversation_id' => $conversation?->id
                ], $requestId);

                return $this->handoverToHuman($conversation, $requestId);
            }

            /*
            |--------------------------------------------------------------------------
            | CACHE
            |--------------------------------------------------------------------------
            */

            $cached = AiCache::where('client_id', $clientId)
                ->where('message_hash', $hash)
                ->first();

            if ($cached) {

                $decoded = json_decode($cached->response, true);

                if (is_array($decoded)) {

                    $this->log('CACHE_HIT', [], $requestId);

                    return $decoded;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | GREETING
            |--------------------------------------------------------------------------
            */

            if ($this->isGreeting($normalized)) {

                return $this->formatResponse(
                    "Hello 👋 How can we assist you?",
                    [],
                    1.0,
                    'system'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | EXACT FAQ MATCH
            |--------------------------------------------------------------------------
            */

            $exact = KnowledgeBase::forClient($clientId)
                ->active()
                ->whereRaw('LOWER(question) = ?', [$normalized])
                ->with('attachments')
                ->first();

            if ($exact) {

                $this->log('FAQ_EXACT_MATCH', [], $requestId);

                return $this->store(
                    $clientId,
                    $hash,
                    $this->formatFromKnowledge($exact, 1.0, 'faq_exact')
                );
            }

            /*
            |--------------------------------------------------------------------------
            | KEYWORD FAQ MATCH
            |--------------------------------------------------------------------------
            */

            $keywordMatch = $this->keywordMatch($clientId, $normalized, $requestId);

            if ($keywordMatch) {

                return $this->store(
                    $clientId,
                    $hash,
                    $keywordMatch
                );
            }

            /*
            |--------------------------------------------------------------------------
            | SEMANTIC RETRIEVAL
            |--------------------------------------------------------------------------
            */

            $candidates = $this->retrieveCandidates($clientId, $normalized, $requestId);

            if (!empty($candidates)) {

                $best = $candidates[0];

                $this->log('SEMANTIC_TOP_MATCH', [
                    'score' => round($best['score'], 4),
                    'question' => $best['knowledge']->question
                ], $requestId);

                if ($best['score'] >= $this->faqThreshold) {

                    return $this->store(
                        $clientId,
                        $hash,
                        $this->formatFromKnowledge(
                            $best['knowledge'],
                            $best['score'],
                            'faq_semantic'
                        )
                    );
                }

                if ($best['score'] >= $this->groundThreshold) {

                    return $this->handleGroundedAI(
                        $clientId,
                        $hash,
                        $normalized,
                        $candidates,
                        $requestId
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | PURE AI
            |--------------------------------------------------------------------------
            */

            $this->log('PURE_AI_MODE', [], $requestId);

            $response = $this->handlePureAI(
                $clientId,
                $hash,
                $normalized,
                $requestId
            );

            if (($response['confidence'] ?? 1) < 0.35) {

                return $this->handoverToHuman($conversation, $requestId);
            }

            return $response;

        } catch (\Throwable $e) {

            Log::error('AIENGINE_FATAL', [
                'error' => $e->getMessage(),
                'request_id' => $requestId
            ]);

            return $this->fallback("Sorry, something went wrong.");
        }
    }

    /*
    |--------------------------------------------------------------------------
    | KEYWORD FAQ MATCH
    |--------------------------------------------------------------------------
    */

    protected function keywordMatch(int $clientId, string $message, string $requestId)
    {

        $items = KnowledgeBase::forClient($clientId)
            ->active()
            ->with('attachments')
            ->get();

        $words = explode(' ', $message);

        $bestScore = 0;
        $best = null;

        foreach ($items as $item) {

            $question = Str::lower($item->question);

            $score = 0;

            foreach ($words as $word) {

                if (strlen($word) < 4) continue;

                if (str_contains($question, $word)) {
                    $score++;
                }
            }

            $score = $score / max(count($words), 1);

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $item;
            }
        }

        if ($best && $bestScore >= 0.30) {

            $this->log('FAQ_KEYWORD_MATCH', [
                'question' => $best->question,
                'score' => $bestScore
            ], $requestId);

            return $this->formatFromKnowledge($best, $bestScore, 'faq_keyword');
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | RETRIEVAL
    |--------------------------------------------------------------------------
    */

    protected function retrieveCandidates(int $clientId, string $message, string $requestId): array
    {

        $queryVector = app(EmbeddingService::class)->generate($message);

        if (!$queryVector) {

            $this->log('EMBEDDING_FAILED', [], $requestId);

            return [];
        }

        $items = KnowledgeBase::forClient($clientId)
            ->active()
            ->whereNotNull('embedding')
            ->with('attachments')
            ->get();

        $results = [];

        foreach ($items as $item) {

            $embedding = is_string($item->embedding)
                ? json_decode($item->embedding, true)
                : $item->embedding;

            if (!is_array($embedding)) continue;

            $score = $this->cosine($queryVector, $embedding);

            $results[] = [
                'knowledge' => $item,
                'score' => $score
            ];
        }

        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($results, 0, $this->candidateLimit);
    }

    protected function cosine(array $a, array $b): float
    {

        $dot = 0;
        $normA = 0;
        $normB = 0;

        foreach ($a as $i => $v) {

            $dot += $v * ($b[$i] ?? 0);
            $normA += $v * $v;
            $normB += ($b[$i] ?? 0) * ($b[$i] ?? 0);
        }

        return $dot / (sqrt($normA) * sqrt($normB) + 1e-10);
    }

    /*
    |--------------------------------------------------------------------------
    | AI MODES
    |--------------------------------------------------------------------------
    */

    protected function handlePureAI(int $clientId, string $hash, string $message, string $requestId): array
    {

        $prompt = "You are a professional visa assistant.\n\nUser: $message";

        $answer = $this->callOpenAI($prompt, $requestId);

        return $this->store(
            $clientId,
            $hash,
            $this->formatResponse(
                $answer ?? 'Please contact support.',
                [],
                0.50,
                'ai'
            )
        );
    }

    protected function handleGroundedAI(
        int $clientId,
        string $hash,
        string $message,
        array $candidates,
        string $requestId
    ): array {

        $context = collect($candidates)
            ->map(fn($c) =>
                "Question: {$c['knowledge']->question}\nAnswer: {$c['knowledge']->answer}"
            )
            ->implode("\n\n");

        $prompt = "Use this information to answer the user question:\n\n$context\n\nUser: $message";

        $answer = $this->callOpenAI($prompt, $requestId);

        return $this->store(
            $clientId,
            $hash,
            $this->formatResponse(
                $answer ?? 'Please contact support.',
                [],
                0.65,
                'grounded_ai'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | OPENAI
    |--------------------------------------------------------------------------
    */

    protected function callOpenAI(string $prompt, string $requestId): ?string
    {

        try {

            $response = Http::withToken(config('services.openai.key'))
                ->timeout($this->timeout)
                ->retry(2, 500)
                ->post('https://api.openai.com/v1/responses', [
                    'model' => $this->model,
                    'input' => $prompt
                ]);

            if ($response->failed()) {

                Log::error('OPENAI_FAILED', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'request_id' => $requestId
                ]);

                return null;
            }

            $json = $response->json();

            return $json['output'][0]['content'][0]['text'] ?? null;

        } catch (\Throwable $e) {

            Log::error('OPENAI_ERROR', [
                'error' => $e->getMessage(),
                'request_id' => $requestId
            ]);

            return null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    protected function normalize(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', Str::lower($text)));
    }

    protected function isGreeting(string $msg): bool
    {
        return in_array($msg, [
            'hi','hello','hey',
            'good morning','good afternoon','good evening'
        ]);
    }

    protected function needsHuman(string $message, ?float $confidence = null): bool
    {

        $keywords = [
            'human',
            'agent',
            'support',
            'representative',
            'talk to someone',
            'customer care',
            'live agent'
        ];

        foreach ($keywords as $word) {

            if (str_contains($message, $word)) {

                Log::info('AI_ESCALATION_KEYWORD', [
                    'keyword' => $word,
                    'message' => $message
                ]);

                return true;
            }
        }

        if ($confidence !== null && $confidence < 0.35) {

            Log::info('AI_ESCALATION_LOW_CONFIDENCE', [
                'confidence' => $confidence
            ]);

            return true;
        }

        return false;
    }

    protected function handoverToHuman($conversation, string $requestId): array
    {

        if ($conversation) {

            $conversation->update([
                'status' => 'human',
                'escalation_reason' => 'ai_escalation',
                'last_activity_at' => now(),
                'escalation_level' => 1,
                'escalation_started_at' => now()
            ]);

            $this->log('ESCALATED_TO_HUMAN', [
                'conversation_id' => $conversation->id
            ], $requestId);

            $router = app(\App\Services\AgentRouter::class);
            $agent = $router->assignAgent($conversation);

            if ($agent) {

                app(\App\Services\AgentNotifier::class)
                    ->notifyAgent($agent, $conversation);
            }
        }

        return [
            'text' => "I'm connecting you to a human agent 👩‍💻 Please wait.",
            'attachments' => [],
            'confidence' => 1,
            'source' => 'handover'
        ];
    }

    protected function formatResponse(string $text, array $attachments, float $confidence, string $source): array
    {
        return compact('text','attachments','confidence','source');
    }

    protected function fallback(string $message = "Please contact support."): array
    {
        return [
            'text' => $message,
            'attachments' => [],
            'confidence' => 0,
            'source' => 'fallback'
        ];
    }

    protected function store(int $clientId, string $hash, array $response): array
    {

        AiCache::updateOrCreate(
            ['client_id' => $clientId, 'message_hash' => $hash],
            ['response' => json_encode($response)]
        );

        return $response;
    }

    protected function log(string $title, array $data, string $requestId): void
    {

        if ($this->debug) {

            Log::info("AIEngine {$title}", array_merge(
                ['request_id' => $requestId],
                $data
            ));
        }
    }
}