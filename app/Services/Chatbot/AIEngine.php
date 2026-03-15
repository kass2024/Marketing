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

    // Common stopwords
    protected array $stopwords = [
        'the', 'and', 'for', 'with', 'from', 'this', 'that', 'what', 'how',
        'can', 'you', 'your', 'have', 'has', 'help', 'need', 'want', 'about',
        'please', 'thanks', 'thank', 'would', 'could', 'should', 'tell',
        'know', 'like', 'just', 'get', 'are', 'not', 'was', 'were'
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
            | STAGE 1: ENHANCED EXACT MATCH (MORE FLEXIBLE)
            |--------------------------------------------------------------------------
            */

            // Try to find any FAQ that closely matches
            $bestMatch = $this->findBestFaqMatch($clientId, $normalized, $originalMessage);
            
            if ($bestMatch) {
                $this->log('FAQ_MATCH_FOUND', [
                    'question' => $bestMatch['knowledge']->question,
                    'score' => $bestMatch['score'],
                    'id' => $bestMatch['knowledge']->id,
                    'method' => $bestMatch['method']
                ], $requestId);

                $response = $this->formatFromKnowledge(
                    $bestMatch['knowledge'], 
                    $bestMatch['score'], 
                    $bestMatch['method']
                );
                
                return $this->store($clientId, $hash, $response);
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
            | STAGE 2: SEMANTIC RETRIEVAL
            |--------------------------------------------------------------------------
            */

            $candidates = $this->retrieveCandidates($clientId, $normalized, $requestId);

            if (!empty($candidates)) {
                $best = $candidates[0];

                $this->log('SEMANTIC_TOP_MATCH', [
                    'score' => round($best['score'], 4),
                    'question' => $best['knowledge']->question,
                    'id' => $best['knowledge']->id
                ], $requestId);

                /*
                |--------------------------------------------------------------------------
                | FAQ SEMANTIC
                |--------------------------------------------------------------------------
                */

                if ($best['score'] >= $this->faqThreshold) {
                    $this->log('FAQ_SEMANTIC_MATCH', [
                        'score' => $best['score']
                    ], $requestId);

                    $response = $this->formatFromKnowledge(
                        $best['knowledge'],
                        $best['score'],
                        'semantic_match'
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
                        $originalMessage,
                        $candidates,
                        $requestId
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | STAGE 3: PURE AI (Last Resort)
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

            return $this->errorResponse();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ENHANCED FAQ MATCHING METHODS
    |--------------------------------------------------------------------------
    */

    /**
     * Find the best FAQ match using multiple strategies
     */
    protected function findBestFaqMatch(int $clientId, string $normalized, string $original): ?array
    {
        $faqs = KnowledgeBase::forClient($clientId)
            ->active()
            ->with('attachments')
            ->get();

        if ($faqs->isEmpty()) {
            return null;
        }

        $bestMatch = null;
        $bestScore = 0;
        $bestMethod = '';

        foreach ($faqs as $faq) {
            $question = Str::lower($faq->question);
            $score = 0;
            $method = '';

            // Strategy 1: Exact match (with punctuation removed)
            $cleanNormalized = preg_replace('/[?¿]/', '', $normalized);
            $cleanQuestion = preg_replace('/[?¿]/', '', $question);
            
            if ($cleanQuestion === $cleanNormalized) {
                $score = 1.0;
                $method = 'exact_match';
            }
            // Strategy 2: Question contains normalized message
            elseif (str_contains($cleanQuestion, $cleanNormalized) && strlen($cleanNormalized) > 5) {
                $score = 0.9;
                $method = 'contains_match';
            }
            // Strategy 3: Normalized message contains question keywords
            else {
                // Extract key terms from both
                $questionKeywords = $this->extractKeywords($question);
                $messageKeywords = $this->extractKeywords($normalized);
                
                if (!empty($messageKeywords)) {
                    $matches = 0;
                    foreach ($messageKeywords as $keyword) {
                        if (in_array($keyword, $questionKeywords) || 
                            str_contains($question, $keyword)) {
                            $matches++;
                        }
                    }
                    
                    if ($matches > 0) {
                        $score = $matches / count($messageKeywords);
                        $method = 'keyword_match';
                    }
                }
            }

            // Track the best match
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $faq;
                $bestMethod = $method;
            }
        }

        // Return if score is good enough
        if ($bestScore >= 0.5) {
            return [
                'knowledge' => $bestMatch,
                'score' => $bestScore,
                'method' => $bestMethod
            ];
        }

        return null;
    }

    /**
     * Extract important keywords from text
     */
    protected function extractKeywords(string $text): array
    {
        $words = explode(' ', $text);
        
        return array_filter($words, function($word) {
            $word = trim($word);
            return strlen($word) >= 3 && !in_array($word, $this->stopwords);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | RETRIEVAL
    |--------------------------------------------------------------------------
    */

    protected function retrieveCandidates(int $clientId, string $message, string $requestId): array
    {
        $embeddingService = app(\App\Services\Chatbot\EmbeddingService::class);
        $queryVector = $embeddingService->generate($message);

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
        $keywords = $this->extractKeywords($message);

        foreach ($items as $item) {
            if (!is_array($item->embedding)) {
                continue;
            }

            // Base cosine similarity
            $score = $this->cosine($queryVector, $item->embedding);

            /*
            |--------------------------------------------------------------------------
            | ENHANCED KEYWORD BOOST
            |--------------------------------------------------------------------------
            */

            $questionText = Str::lower($item->question);
            $boost = 0;

            foreach ($keywords as $keyword) {
                if (str_contains($questionText, $keyword)) {
                    $boost += 0.08; // Higher boost per keyword
                }
            }

            // Cap boost
            $boost = min($boost, 0.30);
            $finalScore = $score + $boost;

            $this->log('SEMANTIC_SCORE', [
                'question' => Str::limit($item->question, 50),
                'base_score' => round($score, 4),
                'boost' => $boost,
                'final_score' => round($finalScore, 4)
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

    /*
    |--------------------------------------------------------------------------
    | AI MODES
    |--------------------------------------------------------------------------
    */

    protected function handlePureAI(int $clientId, string $hash, string $message, string $requestId): array
    {
        $prompt = "You are a professional visa assistant.\n\nUser: $message";

        $answer = $this->callOpenAI($prompt, $requestId);

        $response = $this->formatResponse(
            $answer ?? 'Please contact support.',
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
            ->take(3)
            ->map(fn($c) => 
                "Question: {$c['knowledge']->question}\nAnswer: {$c['knowledge']->answer}"
            )
            ->implode("\n\n");

        $prompt = "
You are a professional visa and immigration assistant working for a visa consultancy.

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
        foreach ($this->humanKeywords as $word) {
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

        // Return EXACT answer from database - no modifications
        return [
            'text'        => $knowledge->answer,
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
            Log::channel('chatbot')->info("AIEngine {$title}", array_merge(
                ['request_id' => $requestId],
                $data
            ));
        }
    }
}