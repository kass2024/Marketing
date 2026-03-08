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

    /*
    |--------------------------------------------------------------------------
    | ENGINE CONFIGURATION
    |--------------------------------------------------------------------------
    */

    protected float $faqThreshold    = 0.52;   // FAQ semantic match
    protected float $groundThreshold = 0.38;   // grounded AI
    protected int   $candidateLimit  = 5;
    protected int   $timeout         = 30;

    protected bool  $debug = true;

    public function __construct()
    {
        $this->model = config('services.openai.model', 'gpt-4.1-mini');
    }

    /*
    |--------------------------------------------------------------------------
    | MAIN ENTRY POINT
    |--------------------------------------------------------------------------
    */

    public function reply(int $clientId, string $message, $conversation = null): array
    {
        $requestId  = Str::uuid()->toString();
        $normalized = $this->normalize($message);
        $hash       = hash('sha256', $clientId . $normalized);

        $this->log('MESSAGE_RECEIVED', [
            'conversation_id' => $conversation?->id,
            'message'         => $normalized
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

            /*
            |--------------------------------------------------------------------------
            | EMPTY MESSAGE
            |--------------------------------------------------------------------------
            */

            if ($normalized === '') {
                return $this->fallback("How can we assist you today?");
            }

            /*
            |--------------------------------------------------------------------------
            | USER REQUESTED HUMAN
            |--------------------------------------------------------------------------
            */

            if ($this->needsHuman($normalized)) {
                return $this->handoverToHuman($conversation, $requestId);
            }

            /*
            |--------------------------------------------------------------------------
            | CACHE LOOKUP
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
            | GREETING DETECTION
            |--------------------------------------------------------------------------
            */

            if ($this->isGreeting($normalized)) {

                return $this->formatResponse(
                    "Hello 👋 How can we assist you today?",
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
                ->whereRaw('LOWER(question) LIKE ?', ["%{$normalized}%"])
                ->with('attachments')
                ->first();

            if ($exact) {

                $this->log('FAQ_EXACT_MATCH', [
                    'question' => $exact->question
                ], $requestId);

                return $this->store(
                    $clientId,
                    $hash,
                    $this->formatFromKnowledge($exact, 1.0, 'faq_exact')
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

                /*
                |--------------------------------------------------------------------------
                | DIRECT FAQ RESPONSE
                |--------------------------------------------------------------------------
                */

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

                /*
                |--------------------------------------------------------------------------
                | GROUNDED AI RESPONSE
                |--------------------------------------------------------------------------
                */

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
            | PURE AI FALLBACK
            |--------------------------------------------------------------------------
            */

            $this->log('PURE_AI_MODE', [], $requestId);

            $response = $this->handlePureAI(
                $clientId,
                $hash,
                $normalized,
                $requestId
            );

            /*
            |--------------------------------------------------------------------------
            | LOW CONFIDENCE ESCALATION
            |--------------------------------------------------------------------------
            */

            if (($response['confidence'] ?? 1) < 0.35) {

                $this->log('AI_LOW_CONFIDENCE_ESCALATION', [
                    'confidence' => $response['confidence']
                ], $requestId);

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
    | RETRIEVAL ENGINE
    |--------------------------------------------------------------------------
    */

    protected function retrieveCandidates(int $clientId, string $message, string $requestId): array
    {
        $queryVector = app(EmbeddingService::class)->generate($message);

        if (!$queryVector || !is_array($queryVector)) {

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

            if (!is_array($item->embedding)) {
                continue;
            }

            $score = $this->cosine($queryVector, $item->embedding);

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
    | AI RESPONSE MODES
    |--------------------------------------------------------------------------
    */

    protected function handlePureAI(int $clientId, string $hash, string $message, string $requestId): array
    {
        $prompt = "
You are a professional visa assistant.

Rules:
- Provide accurate information
- Do not invent facts
- Be clear and concise

User question:
$message
";

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
            ->pluck('knowledge.answer')
            ->implode("\n\n");

        $prompt = "
You are a professional visa assistant.

Use the following knowledge when answering.

Context:
$context

Question:
$message
";

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
    | OPENAI CALL
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

            return $json['output'][0]['content'][0]['text']
                ?? $json['output_text']
                ?? null;

        } catch (\Throwable $e) {

            Log::error('OPENAI_EXCEPTION', [
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
        $greetings = [
            'hi','hello','hey',
            'good morning','good afternoon','good evening'
        ];

        foreach ($greetings as $g) {

            if (str_contains($msg, $g)) {
                return true;
            }
        }

        return false;
    }

    protected function needsHuman(string $message): bool
    {
        $keywords = [
            'human',
            'agent',
            'support',
            'representative',
            'talk to someone',
            'customer care'
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

        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | HUMAN ESCALATION
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | RESPONSE FORMATTERS
    |--------------------------------------------------------------------------
    */

    protected function formatResponse(string $text, array $attachments, float $confidence, string $source): array
    {
        return compact('text','attachments','confidence','source');
    }

    protected function formatFromKnowledge($knowledge, float $confidence, string $source): array
    {
        $attachments = [];

        if ($knowledge->relationLoaded('attachments') && $knowledge->attachments) {

            foreach ($knowledge->attachments as $attachment) {

                if (!$attachment->file_path && !$attachment->url) {
                    continue;
                }

                $type = strtolower($attachment->type ?? 'document');

                if (in_array($type,['jpg','jpeg','png','gif','webp'])) {
                    $type = 'image';
                }

                if (in_array($type,['pdf','doc','docx'])) {
                    $type = 'document';
                }

                $url = asset('storage/' . ltrim($attachment->file_path,'/'));

                $attachments[] = [
                    'type' => $type,
                    'url' => $url,
                    'filename' => basename($attachment->file_path ?? $attachment->url)
                ];
            }
        }

        return [
            'text' => $knowledge->answer ?? '',
            'attachments' => $attachments,
            'confidence' => $confidence,
            'source' => $source
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | FALLBACK
    |--------------------------------------------------------------------------
    */

    protected function fallback(string $message): array
    {
        return [
            'text' => $message,
            'attachments' => [],
            'confidence' => 0,
            'source' => 'fallback'
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | CACHE STORAGE
    |--------------------------------------------------------------------------
    */

    protected function store(int $clientId, string $hash, array $response): array
    {
        AiCache::updateOrCreate(
            [
                'client_id' => $clientId,
                'message_hash' => $hash
            ],
            [
                'response' => json_encode($response)
            ]
        );

        return $response;
    }

    /*
    |--------------------------------------------------------------------------
    | DEBUG LOGGING
    |--------------------------------------------------------------------------
    */

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