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
   protected float $faqThreshold = 0.75;
protected float $groundThreshold = 0.60;
    protected int $candidateLimit = 5;
    protected int $timeout = 30;

    // Disable in production
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

                $this->log('FAQ_SEMANTIC_MODE', [], $requestId);

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
            | GROUNDED AI
            |--------------------------------------------------------------------------
            */

            if ($best['score'] >= $this->groundThreshold) {

                $this->log('GROUNDED_AI_MODE', [], $requestId);

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

      $this->log('NO_KNOWLEDGE_MATCH_ESCALATION', [], $requestId);

return $this->handoverToHuman($conversation, $requestId);

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
            'request_id' => $requestId
        ]);

        return $this->fallback("Sorry, something went wrong.");
    }
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
            $this->log('EMBEDDING FAILED', [], $requestId);
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
            ->pluck('knowledge.answer')
            ->implode("\n\n");

$prompt = "
You are a professional visa and immigration assistant for a visa consultancy.

IMPORTANT RULES:
1. You MUST answer ONLY using the information provided in the CONTEXT section.
2. Do NOT invent, assume, or guess information.
3. If the answer is NOT clearly present in the context, respond exactly with:
   \"I will connect you with a human agent for further assistance.\"
4. Do not provide unrelated information.
5. Keep answers short, clear, and professional.

CONTEXT:
$context

USER QUESTION:
$message

Answer using ONLY the context above.
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

    protected function callOpenAI(string $prompt, string $requestId): ?string
    {
        try {

            $response = Http::withToken(config('services.openai.key'))
                ->timeout($this->timeout)
                ->retry(2, 500)
                ->post('https://api.openai.com/v1/responses', [
                    'model' => $this->model,
                    'input' => $prompt,
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
        'call me',
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

            $url = asset('storage/' . ltrim($attachment->file_path, '/'));

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