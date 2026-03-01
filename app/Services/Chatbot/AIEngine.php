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
    protected float $faqThreshold = 0.60;      // direct FAQ match
    protected float $groundThreshold = 0.45;   // grounded AI
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

        $this->log('START', [
            'client_id' => $clientId,
            'message'   => $normalized
        ], $requestId);

        try {

            if ($normalized === '') {
                return $this->fallback("How can we assist you today?");
            }

            // CACHE
            if ($cached = AiCache::where('client_id', $clientId)
                ->where('message_hash', $hash)
                ->first()) {

                $decoded = json_decode($cached->response, true);

                if (is_array($decoded)) {
                    $this->log('CACHE HIT', [], $requestId);
                    return $decoded;
                }
            }

            // GREETING
            if ($this->isGreeting($normalized)) {
                return $this->formatResponse(
                    "Hello 👋 How can we assist you?",
                    [],
                    1.0,
                    'system'
                );
            }

            // EXACT MATCH (Deterministic)
            $exact = KnowledgeBase::forClient($clientId)
                ->active()
                ->whereRaw('LOWER(question) = ?', [$normalized])
                ->with('attachments')
                ->first();

            if ($exact) {
                $this->log('EXACT FAQ MATCH', [], $requestId);

                return $this->store(
                    $clientId,
                    $hash,
                    $this->formatFromKnowledge($exact, 1.0, 'faq_exact')
                );
            }

            // SEMANTIC RETRIEVAL
            $candidates = $this->retrieveCandidates($clientId, $normalized, $requestId);

            if (!empty($candidates)) {

                $best = $candidates[0];

                $this->log('TOP MATCH', [
                    'score' => round($best['score'], 4),
                    'question' => $best['knowledge']->question
                ], $requestId);

                // FAQ MODE
                if ($best['score'] >= $this->faqThreshold) {

                    $this->log('FAQ MODE', [], $requestId);

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

                // GROUNDED MODE
                if ($best['score'] >= $this->groundThreshold) {

                    $this->log('GROUNDED MODE', [], $requestId);

                    return $this->handleGroundedAI(
                        $clientId,
                        $hash,
                        $normalized,
                        $candidates,
                        $requestId
                    );
                }
            }

            // PURE AI
            $this->log('PURE AI MODE', [], $requestId);

            return $this->handlePureAI(
                $clientId,
                $hash,
                $normalized,
                $requestId
            );

        } catch (\Throwable $e) {

            Log::error('AIEngine FATAL', [
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

        $prompt = "You are a professional visa assistant.
Use the following context when relevant.

Context:
$context

Question:
$message";

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

                $this->log('OPENAI FAILED', [
                    'status' => $response->status()
                ], $requestId);

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

            $url = $attachment->url
                ? url($attachment->url)
                : url($attachment->file_path);

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