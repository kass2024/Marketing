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

    // Optimized thresholds for better accuracy
    protected float $faqThreshold = 0.65;      // Increased for better precision
    protected float $groundThreshold = 0.35;    // Keep as is
    protected int $candidateLimit = 8;          // Increased for better context
    protected int $timeout = 25;                 // Reduced for better UX

    // Enhanced keyword lists
    protected array $greetings = [
        'hi', 'hello', 'hey', 'greetings', 'howdy', 
        'good morning', 'good afternoon', 'good evening',
        'morning', 'afternoon', 'evening'
    ];

    protected array $farewells = [
        'bye', 'goodbye', 'see you', 'talk later', 
        'thank you bye', 'thanks bye'
    ];

    protected array $humanKeywords = [
        'human', 'agent', 'representative', 'person', 'real person',
        'talk to someone', 'speak to human', 'customer service',
        'support team', 'live agent', 'call me', 'contact me',
        'talk to agent', 'connect to human', 'speak to representative',
        'need assistance', 'help me please'
    ];

    protected array $stopwords = [
        'the','and','for','with','from','this','that','what','how',
        'can','you','your','have','has','visa','help','need','want',
        'about','please','thanks','thank','would','could','should',
        'tell','know','like','just','get','are','not'
    ];

    // Disable in production
    protected bool $debug = true;

    public function __construct()
    {
        $this->model = config('services.openai.model', 'gpt-4');
    }

    /*
    |--------------------------------------------------------------------------
    | MAIN ENTRY POINT
    |--------------------------------------------------------------------------
    */

    public function reply(int $clientId, string $message, $conversation = null): array
    {
        $requestId = Str::uuid()->toString();
        $normalized = $this->normalizeMessage($message);
        $hash = hash('sha256', $clientId . $normalized);

        $this->log('MESSAGE_RECEIVED', [
            'conversation_id' => $conversation?->id,
            'original' => $message,
            'normalized' => $normalized
        ], $requestId);

        // HUMAN MODE PROTECTION
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
            // EMPTY MESSAGE
            if ($normalized === '') {
                return $this->greetingResponse();
            }

            // CHECK FOR HUMAN REQUEST
            if ($this->needsHuman($normalized)) {
                $this->log('HUMAN_REQUESTED', [
                    'conversation_id' => $conversation?->id
                ], $requestId);

                return $this->handoverToHuman($conversation, $requestId);
            }

            // CHECK CACHE (1 hour TTL)
            $cached = $this->getCached($clientId, $hash);
            if ($cached) {
                $this->log('CACHE_HIT', [], $requestId);
                return $cached;
            }

            // CHECK GREETINGS
            if ($this->isGreeting($normalized)) {
                return $this->greetingResponse();
            }

            // CHECK FAREWELLS
            if ($this->isFarewell($normalized)) {
                return $this->farewellResponse();
            }

            // EXACT FAQ MATCH
            $exact = $this->findExactMatch($clientId, $normalized);
            if ($exact) {
                $this->log('EXACT_MATCH', [], $requestId);
                return $this->cacheResponse(
                    $clientId,
                    $hash,
                    $this->formatKnowledgeResponse($exact, 1.0, 'exact_match')
                );
            }

            // SEMANTIC RETRIEVAL
            $candidates = $this->retrieveCandidates($clientId, $normalized, $requestId);

            if (!empty($candidates)) {
                $best = $candidates[0];

                $this->log('TOP_CANDIDATE', [
                    'score' => round($best['score'], 4),
                    'question' => Str::limit($best['knowledge']->question, 50)
                ], $requestId);

                // HIGH CONFIDENCE FAQ
                if ($best['score'] >= $this->faqThreshold) {
                    $this->log('FAQ_HIGH_CONFIDENCE', [], $requestId);
                    
                    // Add confidence disclaimer if needed
                    $response = $this->formatKnowledgeResponse(
                        $best['knowledge'],
                        $best['score'],
                        'faq_semantic'
                    );
                    
                    if ($best['score'] < 0.75) {
                        $response['text'] = "ℹ️ *Based on related information:*\n\n" . $response['text'];
                    }
                    
                    return $this->cacheResponse($clientId, $hash, $response);
                }

                // MEDIUM CONFIDENCE - GROUNDED AI
                if ($best['score'] >= $this->groundThreshold) {
                    $this->log('GROUNDED_AI_MODE', [], $requestId);
                    
                    return $this->cacheResponse(
                        $clientId,
                        $hash,
                        $this->handleGroundedAI(
                            $normalized,
                            $candidates,
                            $best['score'],
                            $requestId
                        )
                    );
                }
            }

            // LOW CONFIDENCE - PURE AI WITH DISCLAIMER
            $this->log('PURE_AI_MODE', [], $requestId);

            $response = $this->handlePureAI($normalized, $requestId);
            
            // Add disclaimer for low confidence
            $response['text'] = "I'll do my best to help you with this question:\n\n" . 
                               $response['text'] . 
                               "\n\n⚠️ *For accurate information, you can ask to speak with a human agent.*";
            
            $response['confidence'] = 0.30;

            return $this->cacheResponse($clientId, $hash, $response);

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
    | RETRIEVAL METHODS
    |--------------------------------------------------------------------------
    */

    protected function findExactMatch(int $clientId, string $message): ?KnowledgeBase
    {
        return KnowledgeBase::forClient($clientId)
            ->active()
            ->whereRaw('LOWER(question) = ?', [$message])
            ->orWhereRaw('LOWER(question) LIKE ?', ['%' . $message . '%'])
            ->with('attachments')
            ->first();
    }

    protected function retrieveCandidates(int $clientId, string $message, string $requestId): array
    {
        $embeddingService = app(\App\Services\Chatbot\EmbeddingService::class);
        $queryVector = $embeddingService->generate($message);

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
        $messageWords = $this->extractKeywords($message);

        foreach ($knowledgeBase as $item) {
            if (!is_array($item->embedding)) {
                continue;
            }

            // Calculate base similarity
            $baseScore = $this->cosineSimilarity($queryVector, $item->embedding);
            
            // Apply keyword boost
            $boost = $this->calculateKeywordBoost($messageWords, $item->question);
            $finalScore = min($baseScore + $boost, 1.0);

            // Only keep relevant results
            if ($finalScore > 0.20) {
                $results[] = [
                    'knowledge' => $item,
                    'score' => $finalScore,
                    'base_score' => $baseScore
                ];

                $this->log('CANDIDATE_SCORE', [
                    'question' => Str::limit($item->question, 40),
                    'base' => round($baseScore, 3),
                    'boost' => round($boost, 3),
                    'final' => round($finalScore, 3)
                ], $requestId);
            }
        }

        // Sort by score descending
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($results, 0, $this->candidateLimit);
    }

    protected function cosineSimilarity(array $a, array $b): float
    {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $i => $valueA) {
            $valueB = $b[$i] ?? 0;
            $dotProduct += $valueA * $valueB;
            $normA += $valueA * $valueA;
            $normB += $valueB * $valueB;
        }

        if ($normA == 0 || $normB == 0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB) + 1e-10);
    }

    protected function extractKeywords(string $message): array
    {
        $words = explode(' ', Str::lower($message));
        
        return array_filter($words, function($word) {
            $word = trim($word);
            return strlen($word) >= 3 && !in_array($word, $this->stopwords);
        });
    }

    protected function calculateKeywordBoost(array $messageWords, string $question): float
    {
        $question = Str::lower($question);
        $boost = 0.0;
        $matched = [];

        foreach ($messageWords as $word) {
            if (Str::contains($question, $word) && !in_array($word, $matched)) {
                $matched[] = $word;
                $boost += 0.05;
            }
        }

        return min($boost, 0.25); // Max 25% boost
    }

    /*
    |--------------------------------------------------------------------------
    | AI HANDLERS
    |--------------------------------------------------------------------------
    */

    protected function handleGroundedAI(string $message, array $candidates, float $score, string $requestId): array
    {
        // Build context from top candidates
        $context = collect(array_slice($candidates, 0, 3))
            ->map(fn($c) => "Q: {$c['knowledge']->question}\nA: {$c['knowledge']->answer}")
            ->implode("\n\n---\n\n");

        $prompt = $this->buildGroundedPrompt($message, $context);
        
        $aiResponse = $this->callOpenAI($prompt, $requestId);

        if (!$aiResponse) {
            // Fallback to best candidate if AI fails
            return $this->formatKnowledgeResponse(
                $candidates[0]['knowledge'],
                $score * 0.8,
                'fallback'
            );
        }

        // Add confidence indicator
        $prefix = $score >= 0.45 
            ? "ℹ️ *Based on our knowledge base:*\n\n"
            : "ℹ️ *This might help answer your question:*\n\n";

        return $this->formatResponse(
            $prefix . $aiResponse,
            $candidates[0]['knowledge']->attachments ?? [],
            $score,
            'grounded_ai'
        );
    }

    protected function handlePureAI(string $message, string $requestId): array
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

        return $this->formatResponse(
            $aiResponse,
            [],
            0.40,
            'pure_ai'
        );
    }

    protected function buildGroundedPrompt(string $message, string $context): string
    {
        return <<<PROMPT
You are a professional visa consultant assistant. Use the following knowledge base information to answer the user's question.

CONTEXT (similar questions and answers from our knowledge base):
{$context}

USER QUESTION: {$message}

INSTRUCTIONS:
1. Use ONLY the information from the context above
2. If the context contains relevant information, provide a helpful answer
3. If the context doesn't contain relevant information, say:
   "I don't have specific information about that. Would you like to speak with a human agent?"
4. Be concise, friendly, and professional
5. Do not mention the context or that you're using a knowledge base

YOUR ANSWER:
PROMPT;
    }

    protected function buildPureAIPrompt(string $message): string
    {
        return <<<PROMPT
You are a professional visa consultant assistant for Parrot Canada Visa Consultant Co. Ltd.

IMPORTANT GUIDELINES:
1. Only answer questions related to:
   - Study visas and student permits
   - Work visas and immigration
   - Study abroad programs
   - University admissions
   - Scholarship opportunities
   - Visa documentation requirements
   - General visa咨询 questions

2. If the question is completely unrelated, politely redirect:
   "I specialize in visa and study abroad questions. For other inquiries, please ask to speak with a human agent."

3. Be helpful but accurate. If unsure, suggest speaking with a human agent.

4. Keep responses concise and professional (max 3-4 sentences).

User question: {$message}

Provide a helpful response based on general visa and immigration knowledge:
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
                    'max_tokens' => 400
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

    protected function normalizeMessage(string $text): string
    {
        // Remove HTML tags
        $text = strip_tags($text);
        
        // Remove special characters but keep letters, numbers, and spaces
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        
        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Convert to lowercase and trim
        return Str::lower(trim($text));
    }

    protected function isGreeting(string $message): bool
    {
        return in_array($message, $this->greetings);
    }

    protected function isFarewell(string $message): bool
    {
        foreach ($this->farewells as $farewell) {
            if (str_contains($message, $farewell)) {
                return true;
            }
        }
        return false;
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

    protected function getCached(int $clientId, string $hash): ?array
    {
        $cached = AiCache::where('client_id', $clientId)
            ->where('message_hash', $hash)
            ->where('created_at', '>', now()->subHour()) // 1 hour TTL
            ->first();

        if ($cached) {
            $decoded = json_decode($cached->response, true);
            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    protected function cacheResponse(int $clientId, string $hash, array $response): array
    {
        AiCache::updateOrCreate(
            ['client_id' => $clientId, 'message_hash' => $hash],
            ['response' => json_encode($response)]
        );

        return $response;
    }

    protected function handoverToHuman($conversation, string $requestId): array
    {
        if ($conversation) {
            $conversation->update([
                'status' => 'human',
                'escalation_reason' => 'user_requested',
                'last_activity_at' => now()
            ]);

            $this->log('HANDOVER_COMPLETE', [
                'conversation_id' => $conversation->id
            ], $requestId);
        }

        return [
            'text' => "I'm connecting you with a human agent now. 👨‍💼\n\nPlease wait a moment - they'll respond shortly.",
            'attachments' => [],
            'confidence' => 1.0,
            'source' => 'handover'
        ];
    }

    protected function formatKnowledgeResponse($knowledge, float $confidence, string $source): array
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