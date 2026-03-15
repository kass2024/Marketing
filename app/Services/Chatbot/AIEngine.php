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
    protected float $faqThreshold = 0.55; // Lowered slightly to catch more matches
    protected float $groundThreshold = 0.35;
    protected int $candidateLimit = 10; // Increased for better coverage
    protected int $timeout = 30;

    // Disable in production
    protected bool $debug = true;

    // Enhanced greeting patterns
    protected array $greetings = [
        'hi', 'hello', 'hey', 'greetings', 'howdy',
        'good morning', 'good afternoon', 'good evening',
        'morning', 'afternoon', 'evening', 'hi there',
        'hello there', 'hey there'
    ];

    // Farewell patterns
    protected array $farewells = [
        'bye', 'goodbye', 'see you', 'talk later', 'take care',
        'thanks bye', 'thank you bye', 'bye bye'
    ];

    // Human escalation keywords
    protected array $humanKeywords = [
        'human', 'agent', 'representative', 'person', 'real person',
        'talk to someone', 'speak to human', 'customer service',
        'support team', 'live agent', 'call me', 'contact me',
        'talk to agent', 'connect to human', 'speak to representative',
        'need assistance', 'help me please', 'customer support',
        'live person', 'real agent', 'speak to a person'
    ];

    // Extended stopwords for better keyword extraction
    protected array $stopwords = [
        'the','and','for','with','from','this','that','what','how',
        'can','you','your','have','has','visa','help','need','want',
        'about','please','thanks','thank','would','could','should',
        'tell','know','like','just','get','are','not','was','were',
        'been','being','will','shall','may','might','must','here',
        'there','where','why','when','who','whom','which','any',
        'some','every','all','both','each','few','many','much',
        'such','than','then','them','they','their','ours','yours',
        'myself','yourself','himself','herself','itself','ourselves'
    ];

    // Common FAQ patterns for better matching
    protected array $faqPatterns = [
        'what is', 'what are', 'how to', 'how do', 'how can',
        'where is', 'where are', 'when can', 'when will',
        'why do', 'why is', 'can i', 'do you', 'does the',
        'is there', 'are there', 'tell me about', 'information on',
        'details about', 'explain', 'define', 'meaning of'
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
            | FAREWELL DETECTION
            |--------------------------------------------------------------------------
            */

            if ($this->isFarewell($normalized)) {
                $this->log('FAREWELL_DETECTED', [], $requestId);
                return $this->farewellResponse();
            }

            /*
            |--------------------------------------------------------------------------
            | MULTI-STRATEGY FAQ MATCHING
            |--------------------------------------------------------------------------
            */

            // Strategy 1: Exact match with normalization
            $exactMatch = $this->findExactMatch($clientId, $normalized, $originalMessage);
            if ($exactMatch) {
                $this->log('EXACT_MATCH_FOUND', [
                    'question' => $exactMatch->question
                ], $requestId);

                $response = $this->formatFromKnowledge($exactMatch, 1.0, 'exact_match');
                return $this->store($clientId, $hash, $response);
            }

            // Strategy 2: Keyword-based matching
            $keywordMatch = $this->findKeywordMatch($clientId, $normalized, $originalMessage);
            if ($keywordMatch) {
                $this->log('KEYWORD_MATCH_FOUND', [
                    'question' => $keywordMatch['knowledge']->question,
                    'score' => $keywordMatch['score']
                ], $requestId);

                if ($keywordMatch['score'] >= 0.8) {
                    $response = $this->formatFromKnowledge(
                        $keywordMatch['knowledge'],
                        $keywordMatch['score'],
                        'keyword_match'
                    );
                    return $this->store($clientId, $hash, $response);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | CHECK CACHE (after FAQ attempts)
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
            | SEMANTIC RETRIEVAL WITH ENHANCED BOOSTING
            |--------------------------------------------------------------------------
            */

            $candidates = $this->retrieveCandidates($clientId, $normalized, $originalMessage, $requestId);

            if (!empty($candidates)) {
                $best = $candidates[0];
                $secondBest = $candidates[1] ?? null;

                $this->log('TOP_CANDIDATES', [
                    'first' => [
                        'score' => round($best['score'], 4),
                        'question' => Str::limit($best['knowledge']->question, 50)
                    ],
                    'second' => $secondBest ? [
                        'score' => round($secondBest['score'], 4),
                        'question' => Str::limit($secondBest['knowledge']->question, 50)
                    ] : null
                ], $requestId);

                // High confidence FAQ match
                if ($best['score'] >= $this->faqThreshold) {
                    $this->log('FAQ_HIGH_CONFIDENCE', [
                        'score' => $best['score']
                    ], $requestId);

                    $response = $this->formatFromKnowledge(
                        $best['knowledge'],
                        $best['score'],
                        'semantic_match'
                    );

                    // Add confidence indicator for medium matches
                    if ($best['score'] < 0.7) {
                        $response['text'] = "ℹ️ *Based on related information:*\n\n" . $response['text'];
                    }

                    return $this->store($clientId, $hash, $response);
                }

                // Check if multiple candidates have similar high scores (disambiguation)
                if ($secondBest && ($best['score'] - $secondBest['score']) < 0.1 && $best['score'] > 0.4) {
                    $this->log('AMBIGUOUS_QUERY', [
                        'diff' => round($best['score'] - $secondBest['score'], 4)
                    ], $requestId);

                    return $this->handleAmbiguousQuery(
                        $clientId,
                        $hash,
                        $normalized,
                        [$best, $secondBest],
                        $requestId
                    );
                }

                // Grounded AI with context
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
            | PURE AI WITH DISCLAIMER
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

            // Add disclaimer for AI-generated responses
            $response['text'] = $response['text'] . 
                "\n\n⚠️ *This information is generated by AI. For accurate guidance, you can ask to speak with a human agent.*";

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

            return $this->store($clientId, $hash, $response);

        } catch (\Throwable $e) {
            Log::error('AI_ENGINE_CRITICAL_ERROR', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_id' => $requestId,
                'client_id' => $clientId
            ]);

            return $this->errorResponse();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ENHANCED RETRIEVAL METHODS
    |--------------------------------------------------------------------------
    */

    protected function findExactMatch(int $clientId, string $normalized, string $original): ?KnowledgeBase
    {
        // Try exact match
        $exact = KnowledgeBase::forClient($clientId)
            ->active()
            ->whereRaw('LOWER(question) = ?', [$normalized])
            ->orWhereRaw('LOWER(question) = ?', [Str::lower($original)])
            ->with('attachments')
            ->first();

        if ($exact) {
            return $exact;
        }

        // Try contains match
        $contains = KnowledgeBase::forClient($clientId)
            ->active()
            ->whereRaw('LOWER(question) LIKE ?', ['%' . $normalized . '%'])
            ->orWhereRaw('LOWER(question) LIKE ?', ['%' . Str::lower($original) . '%'])
            ->with('attachments')
            ->first();

        if ($contains) {
            return $contains;
        }

        // Try removing common question patterns
        foreach ($this->faqPatterns as $pattern) {
            if (Str::startsWith($normalized, $pattern)) {
                $withoutPattern = trim(substr($normalized, strlen($pattern)));
                $patternMatch = KnowledgeBase::forClient($clientId)
                    ->active()
                    ->whereRaw('LOWER(question) LIKE ?', ['%' . $withoutPattern . '%'])
                    ->with('attachments')
                    ->first();

                if ($patternMatch) {
                    return $patternMatch;
                }
            }
        }

        return null;
    }

    protected function findKeywordMatch(int $clientId, string $normalized, string $original): ?array
    {
        $keywords = $this->extractKeywords($normalized);
        
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
            $questionWords = explode(' ', Str::lower($item->question));
            $matches = 0;
            $totalWeight = 0;

            foreach ($keywords as $keyword) {
                foreach ($questionWords as $word) {
                    if (str_contains($word, $keyword) || str_contains($keyword, $word)) {
                        $matches++;
                        $totalWeight += strlen($keyword);
                        break;
                    }
                }
            }

            if ($matches > 0) {
                $score = ($totalWeight / strlen($item->question)) * ($matches / count($keywords));
                
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestMatch = $item;
                }
            }
        }

        if ($bestMatch && $bestScore > 0.3) {
            return [
                'knowledge' => $bestMatch,
                'score' => min($bestScore * 1.5, 1.0) // Normalize to 0-1
            ];
        }

        return null;
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
        $keywords = $this->extractKeywords($normalized);
        $messageWords = explode(' ', $normalized);

        foreach ($knowledgeBase as $item) {
            if (!is_array($item->embedding)) {
                continue;
            }

            // Base cosine similarity
            $baseScore = $this->cosine($queryVector, $item->embedding);

            // Enhanced keyword boosting
            $boost = $this->calculateEnhancedBoost($keywords, $messageWords, $item->question);
            
            // Pattern matching boost
            $patternBoost = $this->calculatePatternBoost($normalized, $item->question);
            
            // Length normalization (prefer questions of similar length)
            $lengthFactor = 1 - min(abs(strlen($normalized) - strlen($item->question)) / max(strlen($normalized), strlen($item->question)), 0.3);
            
            $finalScore = ($baseScore + $boost + $patternBoost) * $lengthFactor;
            $finalScore = min($finalScore, 1.0);

            if ($finalScore > 0.20) {
                $results[] = [
                    'knowledge' => $item,
                    'score' => $finalScore,
                    'base_score' => $baseScore,
                    'boost' => $boost,
                    'pattern_boost' => $patternBoost
                ];

                $this->log('CANDIDATE_DETAILS', [
                    'question' => Str::limit($item->question, 40),
                    'base' => round($baseScore, 3),
                    'boost' => round($boost, 3),
                    'pattern' => round($patternBoost, 3),
                    'length' => round($lengthFactor, 3),
                    'final' => round($finalScore, 3)
                ], $requestId);
            }
        }

        // Sort by final score
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($results, 0, $this->candidateLimit);
    }

    protected function calculateEnhancedBoost(array $keywords, array $messageWords, string $question): float
    {
        $question = Str::lower($question);
        $boost = 0.0;
        $matchedKeywords = [];

        // Boost for important keywords
        foreach ($keywords as $keyword) {
            if (str_contains($question, $keyword) && !in_array($keyword, $matchedKeywords)) {
                $matchedKeywords[] = $keyword;
                $boost += 0.08; // Higher boost for keywords
            }
        }

        // Boost for exact word matches
        foreach ($messageWords as $word) {
            if (strlen($word) < 3) continue;
            
            $pattern = '/\b' . preg_quote($word, '/') . '\b/';
            if (preg_match($pattern, $question) && !in_array($word, $matchedKeywords)) {
                $matchedKeywords[] = $word;
                $boost += 0.05;
            }
        }

        return min($boost, 0.35); // Increased max boost
    }

    protected function calculatePatternBoost(string $message, string $question): float
    {
        $boost = 0.0;
        
        // Check if both start with similar question patterns
        foreach ($this->faqPatterns as $pattern) {
            if (Str::startsWith($message, $pattern) && Str::startsWith($question, $pattern)) {
                $boost += 0.1;
                break;
            }
        }

        // Check for key terms that indicate similar question types
        $questionTypes = [
            'what' => ['what', 'which'],
            'how' => ['how'],
            'where' => ['where'],
            'when' => ['when'],
            'why' => ['why'],
            'can' => ['can', 'could'],
            'do' => ['do', 'does']
        ];

        foreach ($questionTypes as $type => $indicators) {
            $messageHas = false;
            $questionHas = false;
            
            foreach ($indicators as $indicator) {
                if (Str::contains($message, $indicator)) $messageHas = true;
                if (Str::contains($question, $indicator)) $questionHas = true;
            }
            
            if ($messageHas && $questionHas) {
                $boost += 0.05;
                break;
            }
        }

        return $boost;
    }

    protected function extractKeywords(string $message): array
    {
        $words = explode(' ', $message);
        
        return array_filter($words, function($word) {
            $word = trim($word);
            return strlen($word) >= 3 && !in_array($word, $this->stopwords);
        });
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
    | HANDLER METHODS
    |--------------------------------------------------------------------------
    */

    protected function handleAmbiguousQuery(int $clientId, string $hash, string $message, array $candidates, string $requestId): array
    {
        $options = collect($candidates)->map(function($c, $index) {
            return ($index + 1) . ". " . $c['knowledge']->question;
        })->implode("\n");

        $response = "I found a few possible answers to your question:\n\n" . $options . 
            "\n\nCould you please clarify which one you're asking about? Or type the number.";

        return $this->store($clientId, $hash, $this->formatResponse(
            $response,
            [],
            0.5,
            'disambiguation'
        ));
    }

    protected function handleGroundedAI(int $clientId, string $hash, string $message, array $candidates, string $requestId): array
    {
        $context = collect($candidates)
            ->take(3)
            ->map(fn($c) => "Q: {$c['knowledge']->question}\nA: {$c['knowledge']->answer}")
            ->implode("\n\n---\n\n");

        $prompt = $this->buildGroundedPrompt($message, $context, $candidates[0]['knowledge']->question);
        
        $aiResponse = $this->callOpenAI($prompt, $requestId);

        if (!$aiResponse) {
            // Fallback to best candidate
            return $this->store($clientId, $hash, $this->formatFromKnowledge(
                $candidates[0]['knowledge'],
                $candidates[0]['score'] * 0.8,
                'grounded_fallback'
            ));
        }

        $response = $this->formatResponse(
            $aiResponse,
            $candidates[0]['knowledge']->attachments ?? [],
            $candidates[0]['score'],
            'grounded_ai'
        );

        return $this->store($clientId, $hash, $response);
    }

    protected function handlePureAI(int $clientId, string $hash, string $message, string $requestId): array
    {
        $prompt = $this->buildPureAIPrompt($message);
        $aiResponse = $this->callOpenAI($prompt, $requestId);

        if (!$aiResponse) {
            return $this->formatResponse(
                "I'm having trouble answering that right now. Would you like to speak with a human agent?",
                [],
                0.20,
                'ai_fallback'
            );
        }

        return $this->store($clientId, $hash, $this->formatResponse(
            $aiResponse,
            [],
            0.40,
            'pure_ai'
        ));
    }

    protected function buildGroundedPrompt(string $message, string $context, string $bestQuestion): string
    {
        return <<<PROMPT
You are a professional visa consultant assistant for Parrot Canada Visa Consultant. 
Use the following similar questions and answers from our knowledge base to answer the user's question.

MOST SIMILAR QUESTION IN OUR DATABASE:
{$bestQuestion}

OTHER RELATED QUESTIONS AND ANSWERS:
{$context}

USER QUESTION: {$message}

INSTRUCTIONS:
1. Use the information from the similar questions to provide the best possible answer
2. If the user's question matches closely with the most similar question, you can adapt that answer
3. Be helpful, concise, and professional
4. If the information doesn't fully answer the question, provide what you can and offer to connect with a human agent
5. Do not mention that you're using a knowledge base or similar questions

YOUR ANSWER:
PROMPT;
    }

    protected function buildPureAIPrompt(string $message): string
    {
        return <<<PROMPT
You are a professional visa consultant assistant for Parrot Canada Visa Consultant Co. Ltd.

SCOPE: Only answer questions related to:
- Study visas and student permits
- Work visas and immigration
- Study abroad programs
- University admissions
- Scholarship opportunities
- Visa documentation requirements
- General visa consultancy questions

USER QUESTION: {$message}

If the question is within scope, provide a helpful, accurate response based on general visa and immigration knowledge.
If the question is outside scope, politely redirect: "I specialize in visa and study abroad questions. For other inquiries, please ask to speak with a human agent."
If you're unsure, suggest speaking with a human agent.

Keep responses concise and professional (2-4 sentences).
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
                        [
                            'role' => 'system',
                            'content' => 'You are a helpful visa consultant assistant. Be concise, professional, and accurate.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'temperature' => 0.3,
                    'max_tokens' => 500
                ]);

            if ($response->failed()) {
                Log::error('OPENAI_API_FAILED', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'request_id' => $requestId
                ]);
                return null;
            }

            $data = $response->json();
            return $data['choices'][0]['message']['content'] ?? null;

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
    | HELPER METHODS
    |--------------------------------------------------------------------------
    */

    protected function normalize(string $text): string
    {
        // Remove extra spaces and normalize
        $text = preg_replace('/\s+/', ' ', trim($text));
        
        // Remove punctuation but keep important characters
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        
        // Remove multiple spaces
        $text = preg_replace('/\s+/', ' ', $text);
        
        return Str::lower(trim($text));
    }

    protected function isGreeting(string $message): bool
    {
        // Check exact matches
        if (in_array($message, $this->greetings)) {
            return true;
        }

        // Check if message starts with greeting
        foreach ($this->greetings as $greeting) {
            if (Str::startsWith($message, $greeting)) {
                return true;
            }
        }

        return false;
    }

    protected function isFarewell(string $message): bool
    {
        foreach ($this->farewells as $farewell) {
            if (Str::contains($message, $farewell)) {
                return true;
            }
        }
        return false;
    }

    protected function needsHuman(string $message): bool
    {
        foreach ($this->humanKeywords as $keyword) {
            if (Str::contains($message, $keyword)) {
                Log::info('HUMAN_ESCALATION_TRIGGERED', [
                    'keyword' => $keyword,
                    'message' => $message
                ]);
                return true;
            }
        }
        return false;
    }

    protected function getCached(int $clientId, string $hash): ?array
    {
        $cached = AiCache::where('client_id', $clientId)
            ->where('message_hash', $hash)
            ->where('created_at', '>', now()->subHours(2)) // 2 hour TTL
            ->first();

        if ($cached) {
            $decoded = json_decode($cached->response, true);
            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    protected function handoverToHuman($conversation, string $requestId): array
    {
        if ($conversation) {
            $conversation->update([
                'status' => 'human',
                'escalation_reason' => 'user_requested',
                'last_activity_at' => now()
            ]);

            $this->log('HANDOVER_INITIATED', [
                'conversation_id' => $conversation->id
            ], $requestId);

            // Dispatch async job for agent assignment
            dispatch(new \App\Jobs\AssignHumanAgent($conversation))->onQueue('high');
        }

        return [
            'text' => "I'm connecting you with a human agent now. 👨‍💼\n\nPlease wait a moment - they'll respond shortly.",
            'attachments' => [],
            'confidence' => 1.0,
            'source' => 'handover'
        ];
    }

    protected function formatFromKnowledge($knowledge, float $confidence, string $source): array
    {
        $attachments = [];

        if ($knowledge->relationLoaded('attachments') && $knowledge->attachments) {
            foreach ($knowledge->attachments as $attachment) {
                $formatted = $this->formatAttachment($attachment);
                if ($formatted) {
                    $attachments[] = $formatted;
                }
            }
        }

        return [
            'text' => $knowledge->answer,
            'attachments' => $attachments,
            'confidence' => $confidence,
            'source' => $source,
            'metadata' => [
                'knowledge_id' => $knowledge->id,
                'question' => $knowledge->question
            ]
        ];
    }

    protected function formatAttachment($attachment): ?array
    {
        if (!$attachment->url && !$attachment->file_path) {
            return null;
        }

        $type = strtolower($attachment->type ?? 'document');

        // Normalize type for WhatsApp
        if (in_array($type, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $type = 'image';
        } elseif (in_array($type, ['pdf', 'doc', 'docx', 'txt'])) {
            $type = 'document';
        }

        $url = $attachment->file_path 
            ? asset('storage/' . ltrim($attachment->file_path, '/'))
            : $attachment->url;

        $filename = $attachment->file_path 
            ? basename($attachment->file_path)
            : basename(parse_url($attachment->url, PHP_URL_PATH) ?? 'attachment');

        return [
            'type' => $type,
            'url' => $url,
            'filename' => $filename,
            'name' => $attachment->name ?? $filename
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

    protected function farewellResponse(): array
    {
        return [
            'text' => "Thank you for chatting with us! 👋 If you have more questions, we're here to help. Have a great day!",
            'attachments' => [],
            'confidence' => 1.0,
            'source' => 'farewell'
        ];
    }

    protected function errorResponse(): array
    {
        return [
            'text' => "I'm experiencing technical difficulties. Please try again in a moment or contact our support team directly.",
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

    protected function log(string $event, array $data, string $requestId): void
    {
        if ($this->debug) {
            Log::channel('chatbot')->info("AI_ENGINE: {$event}", array_merge(
                ['request_id' => $requestId, 'timestamp' => now()->toIso8601String()],
                $data
            ));
        }
    }
}