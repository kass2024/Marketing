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

    // Tunable thresholds - LOWERED for better matching
    protected float $faqThreshold = 0.45; // Lowered from 0.60
    protected float $groundThreshold = 0.30;
    protected int $candidateLimit = 10;
    protected int $timeout = 30;

    // Disable in production
    protected bool $debug = true;

    // Enhanced greeting patterns
    protected array $greetings = [
        'hi','hello','hey','good morning','good afternoon','good evening'
    ];

    // Question patterns for detection
    protected array $questionPatterns = [
        'what', 'how', 'where', 'when', 'why', 'which', 'who',
        'can', 'do', 'does', 'is', 'are', 'will', 'would',
        'could', 'should', 'tell me', 'explain', 'describe'
    ];

    // Critical keywords that MUST trigger FAQ matching
    protected array $criticalKeywords = [
        'document', 'documents', 'required', 'need', 'needs',
        'visa', 'study', 'student', 'application', 'apply',
        'requirement', 'requirements', 'need for', 'what for'
    ];

    public function __construct()
    {
        $this->model = config('services.openai.model', 'gpt-4');
    }

    /*
    |--------------------------------------------------------------------------
    | MAIN ENTRY
    |--------------------------------------------------------------------------
    */

    public function reply(int $clientId, string $message, $conversation = null): array
    {
        $requestId = Str::uuid()->toString();
        $originalMessage = $message; // Keep original for better matching
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
            | GREETING - Check before cache
            |--------------------------------------------------------------------------
            */

            if ($this->isGreeting($normalized)) {
                $this->log('GREETING_DETECTED', [], $requestId);
                return $this->greetingResponse();
            }

            /*
            |--------------------------------------------------------------------------
            | ENHANCED FAQ MATCHING - Check ALL possible matches
            |--------------------------------------------------------------------------
            */

            // Strategy 1: Direct keyword matching for critical topics
            $criticalMatch = $this->findCriticalMatch($clientId, $normalized, $originalMessage);
            if ($criticalMatch) {
                $this->log('CRITICAL_KEYWORD_MATCH', [
                    'question' => $criticalMatch->question
                ], $requestId);

                $response = $this->formatFromKnowledge($criticalMatch, 0.9, 'critical_match');
                $this->store($clientId, $hash, $response);
                return $response;
            }

            // Strategy 2: Question pattern matching
            $questionMatch = $this->findQuestionMatch($clientId, $normalized, $originalMessage);
            if ($questionMatch) {
                $this->log('QUESTION_PATTERN_MATCH', [
                    'question' => $questionMatch->question
                ], $requestId);

                $response = $this->formatFromKnowledge($questionMatch, 0.85, 'question_match');
                $this->store($clientId, $hash, $response);
                return $response;
            }

            // Strategy 3: Exact match (your existing)
            $exact = $this->findExactMatch($clientId, $normalized);
            if ($exact) {
                $this->log('EXACT_MATCH', [
                    'question' => $exact->question
                ], $requestId);

                $response = $this->formatFromKnowledge($exact, 1.0, 'exact_match');
                $this->store($clientId, $hash, $response);
                return $response;
            }

            // Strategy 4: Fuzzy match on key terms
            $fuzzyMatch = $this->findFuzzyMatch($clientId, $normalized, $originalMessage);
            if ($fuzzyMatch) {
                $this->log('FUZZY_MATCH', [
                    'question' => $fuzzyMatch['knowledge']->question,
                    'score' => $fuzzyMatch['score']
                ], $requestId);

                if ($fuzzyMatch['score'] >= 0.5) {
                    $response = $this->formatFromKnowledge(
                        $fuzzyMatch['knowledge'],
                        $fuzzyMatch['score'],
                        'fuzzy_match'
                    );
                    $this->store($clientId, $hash, $response);
                    return $response;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | CACHE - Check after all FAQ attempts
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
            | SEMANTIC RETRIEVAL with enhanced boosting
            |--------------------------------------------------------------------------
            */

            $candidates = $this->retrieveCandidates($clientId, $normalized, $originalMessage, $requestId);

            if (!empty($candidates)) {
                $best = $candidates[0];

                $this->log('TOP_SEMANTIC_MATCH', [
                    'score' => round($best['score'], 4),
                    'question' => $best['knowledge']->question
                ], $requestId);

                /*
                |--------------------------------------------------------------------------
                | FAQ SEMANTIC - Use lower threshold for document-related queries
                |--------------------------------------------------------------------------
                */

                $effectiveThreshold = $this->isDocumentQuery($normalized) ? 0.35 : $this->faqThreshold;

                if ($best['score'] >= $effectiveThreshold) {
                    $this->log('FAQ_SEMANTIC_MATCH', [
                        'score' => $best['score'],
                        'threshold' => $effectiveThreshold
                    ], $requestId);

                    $response = $this->formatFromKnowledge(
                        $best['knowledge'],
                        $best['score'],
                        'semantic_match'
                    );

                    // Add confidence indicator for lower matches
                    if ($best['score'] < 0.6) {
                        $response['text'] = "Based on your question about documents, here's what I found:\n\n" . $response['text'];
                    }

                    $this->store($clientId, $hash, $response);
                    return $response;
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
                        $originalMessage,
                        $candidates,
                        $requestId
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | PURE AI - Last resort
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
    | NEW ENHANCED MATCHING METHODS
    |--------------------------------------------------------------------------
    */

    /**
     * Check if message is asking about documents
     */
    protected function isDocumentQuery(string $message): bool
    {
        $documentKeywords = [
            'document', 'documents', 'paper', 'papers', 'file', 'files',
            'require', 'required', 'need', 'needs', 'what do i need',
            'what documents', 'which documents', 'documents required',
            'visa documents', 'study documents', 'application documents'
        ];

        foreach ($documentKeywords as $keyword) {
            if (str_contains($message, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find critical keyword matches (for important topics like documents)
     */
    protected function findCriticalMatch(int $clientId, string $normalized, string $original): ?KnowledgeBase
    {
        // Check if this is about documents
        if (!$this->isDocumentQuery($normalized)) {
            return null;
        }

        // Look for document-related FAQs
        $documentFaqs = KnowledgeBase::forClient($clientId)
            ->active()
            ->where(function($query) {
                $query->where('question', 'LIKE', '%document%')
                      ->orWhere('question', 'LIKE', '%require%')
                      ->orWhere('question', 'LIKE', '%visa%')
                      ->orWhere('question', 'LIKE', '%need%');
            })
            ->with('attachments')
            ->get();

        if ($documentFaqs->isEmpty()) {
            return null;
        }

        // Score each FAQ
        $bestMatch = null;
        $bestScore = 0;

        foreach ($documentFaqs as $faq) {
            $score = 0;
            $question = Str::lower($faq->question);

            // Check for exact document match
            if (str_contains($question, 'document') && str_contains($normalized, 'document')) {
                $score += 0.5;
            }

            // Check for "study visa" context
            if (str_contains($question, 'study') && str_contains($normalized, 'study')) {
                $score += 0.3;
            }

            // Check for "required" context
            if (str_contains($question, 'required') && str_contains($normalized, 'required')) {
                $score += 0.2;
            }

            // Word overlap
            $questionWords = explode(' ', $question);
            $messageWords = explode(' ', $normalized);
            $common = array_intersect($questionWords, $messageWords);
            $score += count($common) * 0.05;

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $faq;
            }
        }

        return $bestScore > 0.3 ? $bestMatch : null;
    }

    /**
     * Find matches based on question patterns
     */
    protected function findQuestionMatch(int $clientId, string $normalized, string $original): ?KnowledgeBase
    {
        // Check if it's a question
        $isQuestion = false;
        foreach ($this->questionPatterns as $pattern) {
            if (Str::startsWith($normalized, $pattern) || str_contains($normalized, ' ' . $pattern . ' ')) {
                $isQuestion = true;
                break;
            }
        }

        if (!$isQuestion && !str_contains($normalized, '?')) {
            return null;
        }

        // Remove question words to get the core topic
        $coreQuery = $normalized;
        foreach ($this->questionPatterns as $pattern) {
            $coreQuery = str_replace($pattern, '', $coreQuery);
        }
        $coreQuery = trim(str_replace('?', '', $coreQuery));

        // Find FAQs containing the core topic
        return KnowledgeBase::forClient($clientId)
            ->active()
            ->where(function($query) use ($coreQuery, $normalized) {
                $words = explode(' ', $coreQuery);
                foreach ($words as $word) {
                    if (strlen($word) > 3) {
                        $query->orWhere('question', 'LIKE', '%' . $word . '%');
                    }
                }
                // Also check original normalized
                $query->orWhere('question', 'LIKE', '%' . $normalized . '%');
            })
            ->orderByRaw('LENGTH(question) ASC') // Prefer shorter/more direct matches
            ->with('attachments')
            ->first();
    }

    /**
     * Find fuzzy matches based on key terms
     */
    protected function findFuzzyMatch(int $clientId, string $normalized, string $original): ?array
    {
        $keywords = $this->extractImportantKeywords($normalized);
        
        if (empty($keywords)) {
            return null;
        }

        $knowledgeBase = KnowledgeBase::forClient($clientId)
            ->active()
            ->with('attachments')
            ->get();

        $bestMatch = null;
        $bestScore = 0;

        foreach ($knowledgeBase as $item) {
            $question = Str::lower($item->question);
            $matches = 0;
            
            foreach ($keywords as $keyword) {
                if (str_contains($question, $keyword)) {
                    $matches++;
                }
            }

            if ($matches > 0) {
                $score = $matches / count($keywords);
                
                // Boost for exact phrase matches
                $phraseMatch = 0;
                $phrase = implode(' ', array_slice($keywords, 0, 2));
                if (strlen($phrase) > 5 && str_contains($question, $phrase)) {
                    $score += 0.3;
                }

                $finalScore = min($score, 1.0);
                
                if ($finalScore > $bestScore) {
                    $bestScore = $finalScore;
                    $bestMatch = $item;
                }
            }
        }

        if ($bestMatch && $bestScore > 0.3) {
            return [
                'knowledge' => $bestMatch,
                'score' => $bestScore
            ];
        }

        return null;
    }

    /**
     * Extract important keywords (excluding common words)
     */
    protected function extractImportantKeywords(string $message): array
    {
        $stopwords = [
            'the','and','for','with','from','this','that','what','how',
            'can','you','your','have','has','help','need','want','about',
            'please','thanks','thank','would','could','should','tell',
            'know','like','just','get','are','not','was','were','will'
        ];

        $words = explode(' ', $message);
        
        return array_filter($words, function($word) use ($stopwords) {
            $word = trim($word);
            return strlen($word) >= 3 && !in_array($word, $stopwords);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | EXISTING METHODS (with improvements)
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

        // Try contains match
        return KnowledgeBase::forClient($clientId)
            ->active()
            ->whereRaw('LOWER(question) LIKE ?', ['%' . $message . '%'])
            ->with('attachments')
            ->first();
    }

    protected function retrieveCandidates(int $clientId, string $normalized, string $original, string $requestId): array
    {
        $embeddingService = app(\App\Services\Chatbot\EmbeddingService::class);
        $queryVector = $embeddingService->generate($original); // Use original for better embedding

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
        $keywords = $this->extractImportantKeywords($normalized);

        foreach ($knowledgeBase as $item) {
            if (!is_array($item->embedding)) {
                continue;
            }

            // Base cosine similarity
            $baseScore = $this->cosine($queryVector, $item->embedding);

            /*
            |--------------------------------------------------------------------------
            | ENHANCED KEYWORD BOOSTING
            |--------------------------------------------------------------------------
            */

            $boost = 0;
            $questionText = Str::lower($item->question);

            // Boost for each keyword found
            foreach ($keywords as $keyword) {
                if (str_contains($questionText, $keyword)) {
                    $boost += 0.08; // Higher boost
                }
            }

            // Special boost for document queries
            if ($this->isDocumentQuery($normalized) && 
                (str_contains($questionText, 'document') || str_contains($questionText, 'require'))) {
                $boost += 0.15;
            }

            // Cap boost
            $boost = min($boost, 0.35);

            $finalScore = $baseScore + $boost;

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
        
        // Remove punctuation but keep question marks for detection
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
        $keywords = [
            'human', 'agent', 'support', 'representative',
            'talk to someone', 'call me', 'customer care',
            'live agent', 'speak to human'
        ];

        foreach ($keywords as $word) {
            if (str_contains($message, $word)) {
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

    protected function formatFromKnowledge($knowledge, float $confidence, string $source): array
    {
        $attachments = [];

        if ($knowledge->relationLoaded('attachments') && $knowledge->attachments) {
            foreach ($knowledge->attachments as $attachment) {
                if (!$attachment->url && !$attachment->file_path) {
                    continue;
                }

                $type = strtolower($attachment->type ?? 'document');

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