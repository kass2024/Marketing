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

    public function reply(int $clientId, string $message, $conversation = null): array
    {
        $requestId = Str::uuid()->toString();

        Log::info('AIEngine START', compact('requestId','clientId','message'));

        try {

            $message = trim($message);

            if ($message === '') {
                return $this->fallback("How can we assist you today?");
            }

            $normalized = Str::lower($message);
            $hash = hash('sha256', $clientId . $normalized);

            // CACHE
            if ($cached = AiCache::where('client_id',$clientId)
                ->where('message_hash',$hash)->first()) {

                $decoded = json_decode($cached->response, true);

                if (is_array($decoded)) {
                    Log::info('AIEngine CACHE HIT', compact('requestId'));
                    return $decoded;
                }
            }

            // GREETING
            if ($this->isGreeting($normalized)) {
                return $this->formatResponse(
                    "Hello 👋 How can we assist you regarding study or visa services?",
                    [],
                    1.0,
                    'system'
                );
            }

            // RAG
            $candidates = $this->retrieveCandidates($clientId,$message);

            if (!empty($candidates)) {

                $best = $candidates[0];

                Log::info('AIEngine TOP SCORE', [
                    'score'=>$best['score'],
                    'request_id'=>$requestId
                ]);

                if (
                    $best['score'] >= $this->highConfidence &&
                    $this->isStrongMatch($message, $best['knowledge']->question)
                ) {
                    Log::info('AIEngine HIGH CONF FAQ', compact('requestId'));

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

                    Log::info('AIEngine GROUNDED AI', compact('requestId'));

                    return $this->handleGroundedAI(
                        $clientId,
                        $hash,
                        $message,
                        $candidates,
                        $requestId
                    );
                }
            }

            Log::info('AIEngine PURE AI', compact('requestId'));

            return $this->handlePureAI(
                $clientId,
                $hash,
                $message,
                $requestId
            );

        } catch (\Throwable $e) {

            Log::error('AIEngine FATAL', [
                'error'=>$e->getMessage(),
                'request_id'=>$requestId
            ]);

            return $this->fallback("Sorry, something went wrong. Please try again.");
        }
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

                Log::error('OpenAI FAILED', [
                    'status'=>$response->status(),
                    'body'=>$response->body(),
                    'request_id'=>$requestId
                ]);

                return null;
            }

            $json = $response->json();

            Log::info('OpenAI RAW RESPONSE', [
                'request_id'=>$requestId,
                'json'=>$json
            ]);

            // CORRECT extraction
            $text = $json['output'][0]['content'][0]['text'] ?? null;

            return $text ? trim($text) : null;

        } catch (\Throwable $e) {

            Log::error('OpenAI EXCEPTION', [
                'error'=>$e->getMessage(),
                'request_id'=>$requestId
            ]);

            return null;
        }
    }

    protected function handlePureAI(int $clientId,string $hash,string $message,string $requestId): array
    {
        $prompt = "You are a professional visa assistant.\n\nUser: ".$message;

        $answer = $this->callOpenAI($prompt,$requestId);

        if (!$answer) {
            Log::warning('AIEngine PURE AI FALLBACK', compact('requestId'));
            return $this->fallback();
        }

        return $this->store(
            $clientId,
            $hash,
            $this->formatResponse($answer, [], 0.5, 'ai')
        );
    }

    protected function handleGroundedAI(int $clientId,string $hash,string $message,array $candidates,string $requestId): array
    {
        $context = collect($candidates)
            ->pluck('knowledge.answer')
            ->implode("\n\n");

        $prompt = "You are a professional visa assistant.\n\nContext:\n$context\n\nQuestion:\n$message";

        $answer = $this->callOpenAI($prompt,$requestId);

        if (!$answer) {
            Log::warning('AIEngine GROUNDED FALLBACK', compact('requestId'));
            return $this->fallback();
        }

        return $this->store(
            $clientId,
            $hash,
            $this->formatResponse($answer, [], 0.65, 'grounded_ai')
        );
    }

    // RAG + cosine + format + fallback + store same as before
}