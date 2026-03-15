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

    // Tunable thresholds
    protected float $faqThreshold = 0.60;
    protected float $groundThreshold = 0.40;
    protected int $candidateLimit = 5;
    protected int $timeout = 30;

    // Disable in production
    protected bool $debug = true;

    // Add greeting patterns
    protected array $greetings = [
        'hi','hello','hey','good morning','good afternoon','good evening'
    ];

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
            'original' => $message,
            'normalized' => $normalized
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

                $this->log('USER_REQUESTED_AGENT', [
                    'conversation_id' => $conversation?->id
                ], $requestId);

                return $this->handoverToHuman($conversation, $requestId);
            }

            /*
            |--------------------------------------------------------------------------
            | GREETING - Check before cache
            |--------------------------------------------------------------------------
            */

            if ($this->isGreeting($normalized)) {
                $this->log('GREETING_DETECTED', [], $requestId);
                return $this->formatResponse(
                    "Hello! 👋 I'm your virtual assistant from Parrot Canada Visa Consultant.\n\nHow can I help you today? You can ask me about:\n• Visa requirements\n• Study abroad programs\n• Our services\n• Application process\n• Scholarships\n\nOr type 'talk to human' to speak with a real agent.",
                    [],
                    1.0,
                    'greeting'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | EXACT FAQ MATCH - Check before cache
            |--------------------------------------------------------------------------
            */

            $exact = $this->findExactMatch($clientId, $normalized);
            
            if ($exact) {
                $this->log('FAQ_EXACT_MATCH', [
                    'question' => $exact->question
                ], $requestId);

                $response = $this->formatFromKnowledge($exact, 1.0, 'faq_exact');
                
                // Cache the FAQ response
                $this->store($clientId, $hash, $response);
                
                return $response;
            }

            /*
            |--------------------------------------------------------------------------
            | CACHE - Check after exact match
            |--------------------------------------------------------------------------
            */

            $cached = $this->getCached($clientId, $hash);

            if ($cached) {
                $this->log('CACHE_HIT', [
                    'source' => $cached['source'] ?? 'unknown'
                ], $requestId);
                return $cached;
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
                | FAQ SEMANTIC
                |--------------------------------------------------------------------------
                */

                if ($best['score'] >= $this->faqThreshold) {

                    $this->log('FAQ_SEMANTIC_MODE', [
                        'score' => $best['score']
                    ], $requestId);

                    $response = $this->formatFromKnowledge(
                        $best['knowledge'],
                        $best['score'],
                        'faq_semantic'
                    );

                    return $this->store($clientId, $hash, $response);
                }

                /*
                |--------------------------------------------------------------------------
                | GROUNDED AI
                |--------------------------------------------------------------------------
                */

                if ($best['score'] >= $this->groundThreshold) {

                    $this->log('GROUNDED_AI_MODE', [
                        'score' => $best['score']
                    ], $requestId);

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

            $this->log('PURE_AI_MODE', [
                'candidates_count' => count($candidates)
            ], $requestId);

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
                    'confidence' => $response['confidence'] ?? null
                ], $requestId);

                return $this->handoverToHuman($conversation, $requestId);
            }

            return $response;

        } catch (\Throwable $e) {

            Log::error('AIENGINE_FATAL', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_id' => $requestId
            ]);

            return $this->fallback("Sorry, something went wrong. Please try again or contact support.");
        }
    }

    /*
    |--------------------------------------------------------------------------
    | RETRIEVAL
    |--------------------------------------------------------------------------
    */

    protected function findExactMatch(int $clientId, string $message): ?KnowledgeBase
    {
        // Try exact match first
        $exact = KnowledgeBase::forClient($clientId)
            ->active()
            ->whereRaw('LOWER(question) = ?', [$message])
            ->with('attachments')
            ->first();

        if ($exact) {
            return $exact;
        }

        // Try contains match for better coverage
        return KnowledgeBase::forClient($clientId)
            ->active()
            ->whereRaw('LOWER(question) LIKE ?', ['%' . $message . '%'])
            ->with('attachments')
            ->first();
    }

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

            if (!is_array($item->embedding)) {
                continue;
            }

            // Base cosine similarity
            $score = $this->cosine($queryVector, $item->embedding);

            /*
            |--------------------------------------------------------------------------
            | KEYWORD BOOST
            |--------------------------------------------------------------------------
            */

            $questionText = Str::lower($item->question);
            $messageText  = Str::lower($message);

            $boost = 0;

            $stopwords = [
                'the','and','for','with','from','this','that',
                'what','how','can','you','your','have','has',
                'visa','help','need'
            ];

            $words = explode(' ', $messageText);

            foreach ($words as $word) {

                $word = trim($word);

                if (strlen($word) < 4) {
                    continue;
                }

                if (in_array($word, $stopwords)) {
                    continue;
                }

                if (str_contains($questionText, $word)) {
                    $boost += 0.05;
                }
            }

            // Cap boost to avoid overpowering embeddings
            $boost = min($boost, 0.20);

            $score += $boost;

            /*
            |--------------------------------------------------------------------------
            | DEBUG LOG
            |--------------------------------------------------------------------------
            */

            $this->log('SEMANTIC_SCORE', [
                'question' => Str::limit($item->question, 50),
                'base_score' => round($score - $boost, 4),
                'boost' => $boost,
                'final_score' => round($score, 4)
            ], $requestId);

            /*
            |--------------------------------------------------------------------------
            | STORE RESULT
            |--------------------------------------------------------------------------
            */

            $results[] = [
                'knowledge' => $item,
                'score'     => $score
            ];
        }

        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($results, 0, $this->candidateLimit);
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

    protected function getCached(int $clientId, string $hash): ?array
    {
        $cached = AiCache::where('client_id', $clientId)
            ->where('message_hash', $hash)
            ->first();

        if ($cached) {
            $decoded = json_decode($cached->response, true);
            
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | AI MODES
    |--------------------------------------------------------------------------
    */

    protected function handlePureAI(int $clientId, string $hash, string $message, string $requestId): array
    {
        $prompt = "You are a professional visa assistant for Parrot Canada Visa Consultant Co. Ltd.\n\nUser: $message\n\nProvide a helpful response about visas, study abroad, or immigration. If unsure, suggest speaking with a human agent.";

        $answer = $this->callOpenAI($prompt, $requestId);

        $response = $this->formatResponse(
            $answer ?? 'I need to connect you with a human agent for this question.',
            [],
            0.50,
            'pure_ai'
        );

        return $this->store($clientId, $hash, $response);
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

        $prompt = "
You are a professional visa and immigration assistant working for Parrot Canada Visa Consultant.

Your role is to help users by answering questions using the provided knowledge base context.

GUIDELINES:
1. Use the CONTEXT as the primary source of truth.
2. If the user's question is similar to information in the context, provide the closest relevant answer.
3. You may paraphrase or summarize the context to make the answer clearer.
4. Do NOT invent facts that are completely unrelated to the context.
5. If the question is partially related, provide the most helpful information available.
6. If the question is completely unrelated to the context, respond politely with:
   \"I will connect you with a human agent for further assistance.\"
7. Keep answers short, clear, and professional.
8. Do not mention the word 'context' or explain how you generated the answer.

CONTEXT:
$context

USER QUESTION:
$message

Provide the best possible helpful answer using the information above.
";

        $answer = $this->callOpenAI($prompt, $requestId);

        $response = $this->formatResponse(
            $answer ?? 'Please contact support.',
            $candidates[0]['knowledge']->attachments ?? [],
            0.65,
            'grounded_ai'
        );

        return $this->store($clientId, $hash, $response);
    }

    protected function callOpenAI(string $prompt, string $requestId): ?string
    {
        try {

            $response = Http::withToken(config('services.openai.key'))
                ->timeout($this->timeout)
                ->retry(2, 500)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a helpful visa consultant.'],
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'temperature' => 0.3,
                    'max_tokens' => 500
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

            return $json['choices'][0]['message']['content'] ?? null;

        } catch (\Throwable $e) {

            Log::error('OpenAI ERROR', [
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
        return in_array($msg, $this->greetings);
    }

    protected function needsHuman(string $message, ?float $confidence = null): bool
    {
        $keywords = [
            'human',
            'agent',
            'support',
            'representative',
            'talk to someone',
            'call me',
            'customer care',
            'live agent',
            'speak to human'
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
                'confidence' => $confidence,
                'message' => $message
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
                'escalation_reason' => 'user_requested',
                'last_activity_at' => now(),
                'escalation_level' => 1,
                'escalation_started_at' => now()
            ]);

            $this->log('ESCALATED_TO_HUMAN', [
                'conversation_id' => $conversation->id
            ], $requestId);

            try {
                $router = app(\App\Services\AgentRouter::class);
                $agent = $router->assignAgent($conversation);

                if ($agent) {
                    app(\App\Services\AgentNotifier::class)
                        ->notifyAgent($agent, $conversation);
                }
            } catch (\Throwable $e) {
                Log::error('AGENT_ASSIGNMENT_FAILED', [
                    'error' => $e->getMessage(),
                    'conversation_id' => $conversation->id
                ]);
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
        return [
            'text' => $text,
            'attachments' => $attachments,
            'confidence' => $confidence,
            'source' => $source
        ];
    }

    protected function formatFromKnowledge($knowledge, float $confidence, string $source): array
    {
        $attachments = [];

        if ($knowledge->relationLoaded('attachments') && $knowledge->attachments) {

            foreach ($knowledge->attachments as $attachment) {

                // Skip invalid rows
                if (!$attachment->url && !$attachment->file_path) {
                    continue;
                }

                $type = strtolower($attachment->type ?? 'document');

                /*
                |--------------------------------------------------------------------------
                | Normalize Type for WhatsApp API
                |--------------------------------------------------------------------------
                */

                if (in_array($type, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                    $type = 'image';
                }

                if (in_array($type, ['pdf', 'doc', 'docx'])) {
                    $type = 'document';
                }

                /*
                |--------------------------------------------------------------------------
                | Build Public HTTPS URL
                |--------------------------------------------------------------------------
                */

                $url = $attachment->file_path 
                    ? asset('storage/' . ltrim($attachment->file_path, '/'))
                    : $attachment->url;

                /*
                |--------------------------------------------------------------------------
                | Determine Filename
                |--------------------------------------------------------------------------
                */

                $filename = null;

                if ($attachment->file_path) {
                    $filename = basename($attachment->file_path);
                } elseif ($attachment->url) {
                    $filename = basename(parse_url($attachment->url, PHP_URL_PATH));
                }

                /*
                |--------------------------------------------------------------------------
                | Push Attachment
                |--------------------------------------------------------------------------
                */

                $attachments[] = [
                    'type'     => $type,
                    'url'      => $url,
                    'filename' => $filename,
                ];
            }
        }

        return [
            'text'        => $knowledge->answer ?? '',
            'attachments' => $attachments,
            'confidence'  => $confidence,
            'source'      => $source,
        ];
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