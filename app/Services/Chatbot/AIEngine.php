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

    // Tunable thresholds - Optimized for maximum FAQ matching
    protected float $faqThreshold = 0.50; // Balanced threshold
    protected float $groundThreshold = 0.35;
    protected int $candidateLimit = 15; // Increased for better coverage
    protected int $timeout = 30;

    // Disable in production
    protected bool $debug = true;

    // Greeting patterns
    protected array $greetings = [
        'hi', 'hello', 'hey', 'good morning', 'good afternoon', 'good evening',
        'morning', 'afternoon', 'evening', 'hi there', 'hello there'
    ];

    // Human escalation keywords
    protected array $humanKeywords = [
        'human', 'agent', 'support', 'representative', 'talk to someone',
        'call me', 'customer care', 'live agent', 'speak to human'
    ];

    // Critical keywords that MUST trigger FAQ matching
    protected array $criticalKeywords = [
        'document', 'documents', 'required', 'requirement', 'requirements',
        'need', 'needs', 'visa', 'study', 'student', 'application',
        'apply', 'need for', 'what do i need', 'what documents',
        'which documents', 'documents required', 'visa documents',
        'study documents', 'application documents', 'paper', 'papers',
        'form', 'forms', 'certificate', 'certificates', 'transcript',
        'transcripts', 'diploma', 'degree', 'passport', 'photo', 'photos',
        'fee', 'fees', 'proof', 'evidence', 'supporting'
    ];

    public function __construct()
    {
        $this->model = config('services.openai.model', 'gpt-4');
    }

    /*
    |--------------------------------------------------------------------------
    | MAIN ENTRY - PRIORITIZE FAQS ABOVE ALL
    |--------------------------------------------------------------------------
    */

    public function reply(int $clientId, string $message, $conversation = null): array
    {
        $requestId = Str::uuid()->toString();
        $originalMessage = $message;
        $normalized = $this->normalize($message);
        $hash = hash('sha256', $clientId . $normalized);

        $this->log('MESSAGE_RECEIVED', [
            'conversation_id' => $conversation?->id,
            'original' => $originalMessage,
            'normalized' => $normalized
        ], $requestId);

        /*
        |--------------------------------------------------------------------------
        | HUMAN MODE PROTECTION
        |--------------------------------------------------------------------------
        */

        if ($conversation && $conversation->status === 'human') {
            $this->log('HUMAN_MODE_ACTIVE', [
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
                return $this->greetingResponse();
            }

            /*
            |--------------------------------------------------------------------------
            | USER REQUESTED HUMAN
            |--------------------------------------------------------------------------
            */

            if ($this->needsHuman($normalized)) {
                $this->log('HUMAN_REQUESTED', [
                    'conversation_id' => $conversation?->id
                ], $requestId);

                return $this->handoverToHuman($conversation, $requestId);
            }

            /*
            |--------------------------------------------------------------------------
            | GREETING DETECTION
            |--------------------------------------------------------------------------
            */

            if ($this->isGreeting($normalized)) {
                $this->log('GREETING_DETECTED', [], $requestId);
                return $this->greetingResponse();
            }

            /*
            |--------------------------------------------------------------------------
            | STAGE 1: ULTRA FAST KEYWORD MATCHING (BEFORE ANYTHING ELSE)
            | This catches document-related questions immediately
            |--------------------------------------------------------------------------
            */

            // Check if message contains critical keywords
            $hasCriticalKeywords = $this->hasCriticalKeywords($normalized);
            
            if ($hasCriticalKeywords) {
                $this->log('CRITICAL_KEYWORDS_DETECTED', [
                    'keywords' => $this->getMatchedKeywords($normalized)
                ], $requestId);

                // Try to find exact FAQ match based on keywords
                $keywordMatch = $this->findKeywordFaqMatch($clientId, $normalized);
                
                if ($keywordMatch) {
                    $this->log('KEYWORD_FAQ_MATCH', [
                        'question' => $keywordMatch->question,
                        'id' => $keywordMatch->id
                    ], $requestId);

                    $response = $this->formatFromKnowledge($keywordMatch, 1.0, 'keyword_faq');
                    $this->store($clientId, $hash, $response);
                    return $response;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | STAGE 2: EXACT FAQ MATCH (CASE INSENSITIVE)
            |--------------------------------------------------------------------------
            */

            $exactMatch = $this->findExactMatch($clientId, $normalized, $originalMessage);
            
            if ($exactMatch) {
                $this->log('EXACT_FAQ_MATCH', [
                    'question' => $exactMatch->question,
                    'id' => $exactMatch->id
                ], $requestId);

                $response = $this->formatFromKnowledge($exactMatch, 1.0, 'exact_faq');
                $this->store($clientId, $hash, $response);
                return $response;
            }

            /*
            |--------------------------------------------------------------------------
            | STAGE 3: CONTAINS MATCH (QUESTION CONTAINS USER MESSAGE)
            |--------------------------------------------------------------------------
            */

            $containsMatch = $this->findContainsMatch($clientId, $normalized);
            
            if ($containsMatch) {
                $this->log('CONTAINS_FAQ_MATCH', [
                    'question' => $containsMatch->question,
                    'id' => $containsMatch->id
                ], $requestId);

                $response = $this->formatFromKnowledge($containsMatch, 0.95, 'contains_faq');
                $this->store($clientId, $hash, $response);
                return $response;
            }

            /*
            |--------------------------------------------------------------------------
            | STAGE 4: QUESTION PATTERN MATCHING
            |--------------------------------------------------------------------------
            */

            $patternMatch = $this->findPatternMatch($clientId, $normalized);
            
            if ($patternMatch) {
                $this->log('PATTERN_FAQ_MATCH', [
                    'question' => $patternMatch->question,
                    'id' => $patternMatch->id
                ], $requestId);

                $response = $this->formatFromKnowledge($patternMatch, 0.9, 'pattern_faq');
                $this->store($clientId, $hash, $response);
                return $response;
            }

            /*
            |--------------------------------------------------------------------------
            | CHECK CACHE (After FAQ attempts)
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
            | STAGE 5: SEMANTIC SEARCH WITH BOOSTING
            |--------------------------------------------------------------------------
            */

            $candidates = $this->retrieveCandidates($clientId, $normalized, $originalMessage, $requestId);

            if (!empty($candidates)) {
                $best = $candidates[0];

                $this->log('TOP_SEMANTIC_MATCH', [
                    'score' => round($best['score'], 4),
                    'question' => $best['knowledge']->question,
                    'id' => $best['knowledge']->id
                ], $requestId);

                // FAQ SEMANTIC MATCH - Return exact FAQ answer
                if ($best['score'] >= $this->faqThreshold) {
                    $this->log('SEMANTIC_FAQ_MATCH', [
                        'score' => $best['score']
                    ], $requestId);

                    $response = $this->formatFromKnowledge(
                        $best['knowledge'],
                        $best['score'],
                        'semantic_faq'
                    );

                    $this->store($clientId, $hash, $response);
                    return $response;
                }

                // GROUNDED AI - Only when no good FAQ match
                if ($best['score'] >= $this->groundThreshold) {
                    $this->log('GROUNDED_AI_MODE', [
                        'score' => $best['score']
                    ], $requestId);

                    return $this->handleGroundedAI(
                        $clientId,
                        $hash,
                        $originalMessage,
                        $candidates,
                        $requestId
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | STAGE 6: PURE AI (LAST RESORT)
            |--------------------------------------------------------------------------
            */

            $this->log('PURE_AI_MODE', [
                'candidates_count' => count($candidates)
            ], $requestId);

            $response = $this->handlePureAI(
                $clientId,
                $hash,
                $originalMessage,
                $requestId
            );

            /*
            |--------------------------------------------------------------------------
            | LOW CONFIDENCE ESCALATION
            |--------------------------------------------------------------------------
            */

            if (($response['confidence'] ?? 1) < 0.35) {
                $this->log('LOW_CONFIDENCE_ESCALATION', [
                    'confidence' => $response['confidence'] ?? null
                ], $requestId);

                return $this->handoverToHuman($conversation, $requestId);
            }

            return $response;

        } catch (\Throwable $e) {
            Log::error('AI_ENGINE_CRITICAL_ERROR', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_id' => $requestId
            ]);

            return $this->errorResponse();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ENHANCED FAQ MATCHING METHODS
    |--------------------------------------------------------------------------
    */

    /**
     * Check if message contains critical keywords
     */
    protected function hasCriticalKeywords(string $message): bool
    {
        foreach ($this->criticalKeywords as $keyword) {
            if (str_contains($message, $keyword)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get matched keywords for logging
     */
    protected function getMatchedKeywords(string $message): array
    {
        $matched = [];
        foreach ($this->criticalKeywords as $keyword) {
            if (str_contains($message, $keyword)) {
                $matched[] = $keyword;
            }
        }
        return $matched;
    }

    /**
     * Find FAQ based on keyword matching
     */
    protected function findKeywordFaqMatch(int $clientId, string $message): ?KnowledgeBase
    {
        // Special handling for document-related queries
        if (str_contains($message, 'document') || str_contains($message, 'require')) {
            // Look for the specific study visa document FAQ
            $documentFaq = KnowledgeBase::forClient($clientId)
                ->active()
                ->where('question', 'LIKE', '%document%required%study%visa%')
                ->orWhere('question', 'LIKE', '%what%document%study%visa%')
                ->orWhere('question', 'LIKE', '%document%need%study%visa%')
                ->with('attachments')
                ->first();

            if ($documentFaq) {
                return $documentFaq;
            }
        }

        // General keyword matching
        $keywords = $this->extractKeywords($message);
        
        if (empty($keywords)) {
            return null;
        }

        $query = KnowledgeBase::forClient($clientId)->active();
        
        foreach ($keywords as $keyword) {
            if (strlen($keyword) > 2) {
                $query->orWhere('question', 'LIKE', '%' . $keyword . '%');
            }
        }

        return $query->with('attachments')->first();
    }

    /**
     * Extract meaningful keywords from message
     */
    protected function extractKeywords(string $message): array
    {
        $stopwords = [
            'the', 'and', 'for', 'with', 'from', 'this', 'that', 'what',
            'how', 'can', 'you', 'your', 'have', 'has', 'help', 'need',
            'want', 'about', 'please', 'thanks', 'thank', 'would', 'could',
            'should', 'tell', 'know', 'like', 'just', 'get', 'are', 'not'
        ];

        $words = explode(' ', $message);
        
        return array_filter($words, function($word) use ($stopwords) {
            $word = trim($word);
            return strlen($word) >= 3 && !in_array($word, $stopwords);
        });
    }

    /**
     * Find exact match with multiple strategies
     */
    protected function findExactMatch(int $clientId, string $normalized, string $original): ?KnowledgeBase
    {
        // Try exact normalized match
        $exact = KnowledgeBase::forClient($clientId)
            ->active()
            ->whereRaw('LOWER(question) = ?', [$normalized])
            ->with('attachments')
            ->first();

        if ($exact) {
            return $exact;
        }

        // Try original message
        $originalMatch = KnowledgeBase::forClient($clientId)
            ->active()
            ->whereRaw('LOWER(question) = ?', [Str::lower($original)])
            ->with('attachments')
            ->first();

        if ($originalMatch) {
            return $originalMatch;
        }

        // Try without question marks
        $withoutQuestion = str_replace('?', '', $normalized);
        $withoutQuestionMatch = KnowledgeBase::forClient($clientId)
            ->active()
            ->whereRaw('LOWER(question) = ?', [$withoutQuestion])
            ->with('attachments')
            ->first();

        if ($withoutQuestionMatch) {
            return $withoutQuestionMatch;
        }

        return null;
    }

    /**
     * Find contains match (question contains user message)
     */
    protected function findContainsMatch(int $clientId, string $message): ?KnowledgeBase
    {
        // If message is too short, don't use contains match
        if (strlen($message) < 5) {
            return null;
        }

        return KnowledgeBase::forClient($clientId)
            ->active()
            ->where('question', 'LIKE', '%' . $message . '%')
            ->with('attachments')
            ->first();
    }

    /**
     * Find pattern-based match (removing common question prefixes)
     */
    protected function findPatternMatch(int $clientId, string $message): ?KnowledgeBase
    {
        $patterns = [
            'what is', 'what are', 'how to', 'how do', 'how can',
            'where is', 'where are', 'when can', 'when will',
            'why do', 'why is', 'can i', 'do you', 'does the',
            'tell me about', 'tell me', 'i want to know', 'i need to know'
        ];

        $cleanMessage = $message;

        foreach ($patterns as $pattern) {
            if (Str::startsWith($message, $pattern)) {
                $cleanMessage = trim(substr($message, strlen($pattern)));
                break;
            }
        }

        // Remove question mark if present
        $cleanMessage = str_replace('?', '', $cleanMessage);

        // If after cleaning we have meaningful text, try to match
        if (strlen($cleanMessage) > 3) {
            return KnowledgeBase::forClient($clientId)
                ->active()
                ->where(function($query) use ($cleanMessage) {
                    $words = explode(' ', $cleanMessage);
                    foreach ($words as $word) {
                        if (strlen($word) > 3) {
                            $query->orWhere('question', 'LIKE', '%' . $word . '%');
                        }
                    }
                })
                ->with('attachments')
                ->first();
        }

        return null;
    }

    /**
     * Retrieve candidates using semantic search
     */
    protected function retrieveCandidates(int $clientId, string $normalized, string $original, string $requestId): array
    {
        $embeddingService = app(\App\Services\Chatbot\EmbeddingService::class);
        $queryVector = $embeddingService->generate($original);

        if (!$queryVector) {
            $this->log('EMBEDDING_FAILED', [], $requestId);
            return [];
        }

        $knowledgeBase = KnowledgeBase::forClient($clientId)
            ->active()
            ->whereNotNull('embedding')
            ->with('attachments')
            ->get();

        $results = [];
        $keywords = $this->extractKeywords($normalized);

        foreach ($knowledgeBase as $item) {
            if (!is_array($item->embedding)) {
                continue;
            }

            // Base cosine similarity
            $baseScore = $this->cosine($queryVector, $item->embedding);

            // Keyword boosting
            $boost = 0;
            $questionText = Str::lower($item->question);

            foreach ($keywords as $keyword) {
                if (str_contains($questionText, $keyword)) {
                    $boost += 0.10;
                }
            }

            // Special boost for document queries
            if ($this->hasCriticalKeywords($normalized) && 
                (str_contains($questionText, 'document') || str_contains($questionText, 'required'))) {
                $boost += 0.20;
            }

            $boost = min($boost, 0.40);
            $finalScore = min($baseScore + $boost, 1.0);

            $this->log('CANDIDATE_SCORE', [
                'question' => Str::limit($item->question, 40),
                'base' => round($baseScore, 3),
                'boost' => round($boost, 3),
                'final' => round($finalScore, 3)
            ], $requestId);

            $results[] = [
                'knowledge' => $item,
                'score' => $finalScore
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
            ->where('created_at', '>', now()->subHour())
            ->first();

        if ($cached) {
            $decoded = json_decode($cached->response, true);
            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | AI HANDLERS (Only used when NO FAQ matches)
    |--------------------------------------------------------------------------
    */

    protected function handleGroundedAI(int $clientId, string $hash, string $message, array $candidates, string $requestId): array
    {
        $context = collect($candidates)
            ->take(3)
            ->map(fn($c) => "Q: {$c['knowledge']->question}\nA: {$c['knowledge']->answer}")
            ->implode("\n\n---\n\n");

        $prompt = $this->buildGroundedPrompt($message, $context);
        $aiResponse = $this->callOpenAI($prompt, $requestId);

        if (!$aiResponse) {
            // Fallback to best candidate
            return $this->store($clientId, $hash, $this->formatFromKnowledge(
                $candidates[0]['knowledge'],
                $candidates[0]['score'] * 0.8,
                'grounded_fallback'
            ));
        }

        return $this->store($clientId, $hash, $this->formatResponse(
            $aiResponse,
            $candidates[0]['knowledge']->attachments ?? [],
            $candidates[0]['score'],
            'grounded_ai'
        ));
    }

    protected function handlePureAI(int $clientId, string $hash, string $message, string $requestId): array
    {
        $prompt = "You are a professional visa assistant for Parrot Canada Visa Consultant Co. Ltd.\n\nUser: $message\n\nProvide a helpful response about visas, study abroad, or immigration. If unsure, suggest speaking with a human agent.";

        $answer = $this->callOpenAI($prompt, $requestId);

        return $this->store($clientId, $hash, $this->formatResponse(
            $answer ?? 'I need to connect you with a human agent for this question.',
            [],
            0.50,
            'pure_ai'
        ));
    }

    protected function buildGroundedPrompt(string $message, string $context): string
    {
        return <<<PROMPT
You are a professional visa and immigration assistant working for Parrot Canada Visa Consultant.

Use the following similar questions and answers from our knowledge base to answer the user's question.

CONTEXT:
{$context}

USER QUESTION: {$message}

INSTRUCTIONS:
1. Use the context as your primary source
2. Provide a helpful, accurate answer
3. Be concise and professional
4. Do not mention the context

YOUR ANSWER:
PROMPT;
    }

    protected function callOpenAI(string $prompt, string $requestId): ?string
    {
        try {
            $response = Http::withToken(config('services.openai.key'))
                ->timeout($this->timeout)
                ->retry(2, 1000)
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
    | HELPER METHODS
    |--------------------------------------------------------------------------
    */

    protected function normalize(string $text): string
    {
        // Remove extra spaces
        $text = preg_replace('/\s+/', ' ', trim($text));
        
        // Remove punctuation but keep question marks
        $text = preg_replace('/[^\p{L}\p{N}\s\?]/u', ' ', $text);
        
        // Remove multiple spaces
        $text = preg_replace('/\s+/', ' ', $text);
        
        return Str::lower(trim($text));
    }

    protected function isGreeting(string $msg): bool
    {
        return in_array($msg, $this->greetings);
    }

    protected function needsHuman(string $message): bool
    {
        foreach ($this->humanKeywords as $keyword) {
            if (str_contains($message, $keyword)) {
                return true;
            }
        }
        return false;
    }

    protected function handoverToHuman($conversation, string $requestId): array
    {
        if ($conversation) {
            $conversation->update([
                'status' => 'human',
                'escalation_reason' => 'user_requested',
                'last_activity_at' => now()
            ]);

            $this->log('ESCALATED_TO_HUMAN', [
                'conversation_id' => $conversation->id
            ], $requestId);
        }

        return [
            'text' => "I'm connecting you to a human agent 👩‍💻 Please wait.",
            'attachments' => [],
            'confidence' => 1,
            'source' => 'handover'
        ];
    }

    /**
     * CRITICAL: Format FAQ response EXACTLY as stored in database
     * This returns the raw FAQ answer without any modifications
     */
    protected function formatFromKnowledge($knowledge, float $confidence, string $source): array
    {
        $attachments = [];

        // Get attachments exactly as stored
        if ($knowledge->relationLoaded('attachments') && $knowledge->attachments) {
            foreach ($knowledge->attachments as $attachment) {
                if (!$attachment->url && !$attachment->file_path) {
                    continue;
                }

                $type = strtolower($attachment->type ?? 'document');

                // Normalize type for WhatsApp
                if (in_array($type, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                    $type = 'image';
                }

                if (in_array($type, ['pdf', 'doc', 'docx'])) {
                    $type = 'document';
                }

                $url = $attachment->file_path 
                    ? asset('storage/' . ltrim($attachment->file_path, '/'))
                    : $attachment->url;

                $filename = $attachment->file_path 
                    ? basename($attachment->file_path)
                    : basename(parse_url($attachment->url, PHP_URL_PATH));

                $attachments[] = [
                    'type'     => $type,
                    'url'      => $url,
                    'filename' => $filename,
                    'name'     => $attachment->name ?? $filename
                ];
            }
        }

        // Return the EXACT answer from database - NO modifications, NO prefixes
        return [
            'text'        => $knowledge->answer, // EXACT as stored
            'attachments' => $attachments,
            'confidence'  => $confidence,
            'source'      => $source,
            'metadata'    => [
                'knowledge_id' => $knowledge->id,
                'question'     => $knowledge->question
            ]
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

    protected function greetingResponse(): array
    {
        return [
            'text' => "Hello! 👋 I'm your virtual assistant from Parrot Canada Visa Consultant.\n\nHow can I help you today? You can ask me about:\n• Visa requirements\n• Study abroad programs\n• Our services\n• Application process\n• Scholarships\n\nOr type 'talk to human' to speak with a real agent.",
            'attachments' => [],
            'confidence' => 1.0,
            'source' => 'greeting'
        ];
    }

    protected function errorResponse(): array
    {
        return [
            'text' => "I'm experiencing technical difficulties. Please try again in a moment.",
            'attachments' => [],
            'confidence' => 0,
            'source' => 'error'
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