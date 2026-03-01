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
    protected float $highConfidence = 0.82;
    protected float $mediumConfidence = 0.60;
    protected int $candidateLimit = 5;
    protected int $timeout = 30;

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

        Log::info('AIEngine START', [
            'request_id' => $requestId,
            'client_id'  => $clientId,
            'message'    => $message
        ]);

        try {

            $message = trim($message);

            if ($message === '') {
                return $this->fallback("How can we assist you today?");
            }

            $normalized = Str::lower($message);
            $hash = hash('sha256', $clientId . $normalized);

            // ---------------- CACHE ----------------
            if ($cached = AiCache::where('client_id', $clientId)
                ->where('message_hash', $hash)
                ->first()) {

                $decoded = json_decode($cached->response, true);

                if (is_array($decoded)) {
                    Log::info('AIEngine CACHE HIT', ['request_id' => $requestId]);
                    return $decoded;
                }

                Log::warning('AIEngine corrupted cache', ['request_id' => $requestId]);
            }

            // ---------------- GREETING ----------------
            if ($this->isGreeting($normalized)) {
                return $this->formatResponse(
                    "Hello 👋 How can we assist you regarding study or visa services?",
                    [],
                    1.0,
                    'system'
                );
            }

            // ---------------- RAG ----------------
            $candidates = $this->retrieveCandidates($clientId, $message);

            if (!empty($candidates)) {

                $best = $candidates[0];

                Log::info('AIEngine TOP SCORE', [
                    'score'      => $best['score'],
                    'request_id' => $requestId
                ]);

                if (
                    $best['score'] >= $this->highConfidence &&
                    $this->isStrongMatch($message, $best['knowledge']->question)
                ) {
                    Log::info('AIEngine FAQ MATCH', ['request_id' => $requestId]);

                    return $this->store(
                        $clientId,
                        $hash,
                        $this->formatFromKnowledge(
                            $best['knowledge'],
                            $best['score'],
                            'faq'
                        )
                    );
                }

                if ($best['score'] >= $this->mediumConfidence) {

                    Log::info('AIEngine GROUNDED AI', ['request_id' => $requestId]);

                    return $this->handleGroundedAI(
                        $clientId,
                        $hash,
                        $message,
                        $candidates,
                        $requestId
                    );
                }
            }

            Log::info('AIEngine PURE AI', ['request_id' => $requestId]);

            return $this->handlePureAI(
                $clientId,
                $hash,
                $message,
                $requestId
            );

        } catch (\Throwable $e) {

            Log::error('AIEngine FATAL', [
                'error'      => $e->getMessage(),
                'request_id' => $requestId
            ]);

            return $this->fallback(
                "Sorry, something went wrong. Please try again."
            );
        }
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
                    'input' => $prompt,
                ]);

            if ($response->failed()) {

                Log::error('OpenAI FAILED', [
                    'status'     => $response->status(),
                    'body'       => $response->body(),
                    'request_id' => $requestId
                ]);

                return null;
            }

            $json = $response->json();

            Log::info('OpenAI RAW RESPONSE', [
                'request_id' => $requestId,
            ]);

            // Correct parsing for Responses API
            $text = $json['output'][0]['content'][0]['text'] ?? null;

            return $text ? trim($text) : null;

        } catch (\Throwable $e) {

            Log::error('OpenAI EXCEPTION', [
                'error'      => $e->getMessage(),
                'request_id' => $requestId
            ]);

            return null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PURE AI
    |--------------------------------------------------------------------------
    */

    protected function handlePureAI(
        int $clientId,
        string $hash,
        string $message,
        string $requestId
    ): array {

        $prompt = "You are a professional visa assistant.\n\nUser: " . $message;

        $answer = $this->callOpenAI($prompt, $requestId);

        if (!$answer) {
            return $this->fallback();
        }

        return $this->store(
            $clientId,
            $hash,
            $this->formatResponse($answer, [], 0.5, 'ai')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | GROUNDED AI
    |--------------------------------------------------------------------------
    */

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

        $prompt = "You are a professional visa assistant.\n\nContext:\n$context\n\nQuestion:\n$message";

        $answer = $this->callOpenAI($prompt, $requestId);

        if (!$answer) {
            return $this->fallback();
        }

        return $this->store(
            $clientId,
            $hash,
            $this->formatResponse($answer, [], 0.65, 'grounded_ai')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | RAG RETRIEVAL
    |--------------------------------------------------------------------------
    */

    protected function retrieveCandidates(int $clientId, string $message): array
    {
        $queryVector = app(EmbeddingService::class)->generate($message);

        if (!$queryVector) {
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

    protected function isStrongMatch(string $input, ?string $faqQuestion): bool
    {
        if (!$faqQuestion) {
            return false;
        }

        $inputWords = collect(explode(' ', strtolower($input)));
        $faqWords   = collect(explode(' ', strtolower($faqQuestion)));

        return $inputWords->intersect($faqWords)->count() >= 2;
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    protected function isGreeting(string $msg): bool
    {
        return in_array($msg, [
            'hi',
            'hello',
            'hey',
            'good morning',
            'good afternoon',
            'good evening'
        ]);
    }

    protected function formatResponse(
        string $text,
        array $attachments,
        float $confidence,
        string $source
    ): array {
        return [
            'text'        => $text,
            'attachments' => $attachments,
            'confidence'  => $confidence,
            'source'      => $source
        ];
    }

    protected function formatFromKnowledge(
        $knowledge,
        float $confidence,
        string $source
    ): array {
        return [
            'text'        => $knowledge->answer,
            'attachments' => $knowledge->attachments->map(function ($att) {
                return [
                    'type'      => $att->type,
                    'file_path' => $att->file_path,
                    'url'       => $att->url,
                ];
            })->toArray(),
            'confidence'  => $confidence,
            'source'      => $source
        ];
    }

    protected function fallback(string $message = "Please contact our team for assistance."): array
    {
        return [
            'text'        => $message,
            'attachments' => [],
            'confidence'  => 0,
            'source'      => 'fallback'
        ];
    }

    protected function store(int $clientId, string $hash, array $response): array
    {
        AiCache::updateOrCreate(
            [
                'client_id'    => $clientId,
                'message_hash' => $hash
            ],
            [
                'response' => json_encode($response)
            ]
        );

        return $response;
    }
}