<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\KnowledgeBase;
use App\Models\AiCache;

class AIEngine
{
    protected float $highConfidence = 0.72;
    protected float $mediumConfidence = 0.55;
    protected int $candidateLimit = 5;
    protected int $timeout = 25;

    /*
    |--------------------------------------------------------------------------
    | MAIN ENTRY
    |--------------------------------------------------------------------------
    */
    public function reply(int $clientId, string $message, $conversation = null): string
    {
        $message = trim($message);

        if ($message === '') {
            return "How can we assist you today?";
        }

        $normalized = Str::lower($message);
        $hash = hash('sha256', $clientId . $normalized);

        // ✅ Cache first
        if ($cached = AiCache::where('client_id',$clientId)
            ->where('message_hash',$hash)->first()) {
            return $cached->response;
        }

        // ✅ Greeting
        if ($this->isGreeting($normalized)) {
            return "Hello 👋 How can we assist you regarding study or visa services?";
        }

        // ✅ Intent
        $intent = $this->classifyIntent($message);
        Log::info('Intent detected: '.$intent);

        // ✅ Always retrieve candidates (RAG first)
        $candidates = $this->retrieveCandidates($clientId,$message);

        if (!empty($candidates)) {

            $best = $candidates[0];
            Log::info('Top similarity score: '.$best['score']);

            // 🔥 HIGH CONFIDENCE → Direct FAQ
            if ($best['score'] >= $this->highConfidence) {
                return $this->store($clientId,$hash,$best['answer']);
            }

            // 🔥 MEDIUM CONFIDENCE → AI with context grounding
            if ($best['score'] >= $this->mediumConfidence) {
                return $this->handleGroundedAI(
                    $clientId,
                    $hash,
                    $message,
                    $candidates
                );
            }
        }

        // 🔥 LOW CONFIDENCE → Pure AI advisory
        return $this->handlePureAI($clientId,$hash,$message);
    }

    protected function isGreeting(string $msg): bool
    {
        return in_array($msg,['hi','hello','hey','good morning','good afternoon','good evening']);
    }

    /*
    |--------------------------------------------------------------------------
    | INTENT CLASSIFIER (SAFE FALLBACK)
    |--------------------------------------------------------------------------
    */
    protected function classifyIntent(string $message): string
    {
        $apiKey = config('services.openai.key');
        if (!$apiKey) return 'advisory';

        try {

            $response = Http::withToken($apiKey)
                ->timeout(10)
                ->retry(2,300)
                ->post('https://api.openai.com/v1/chat/completions',[
                    'model'=>'gpt-4o-mini',
                    'messages'=>[
                        ['role'=>'system','content'=>'Classify as faq or advisory. Return only one word.'],
                        ['role'=>'user','content'=>$message]
                    ],
                    'temperature'=>0
                ]);

            if ($response->failed()) return 'advisory';

            $intent = trim($response->json('choices.0.message.content'));

            return in_array($intent,['faq','advisory']) ? $intent : 'advisory';

        } catch (\Throwable $e) {
            Log::error('Intent classification failed: '.$e->getMessage());
            return 'advisory';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | RETRIEVAL
    |--------------------------------------------------------------------------
    */
    protected function retrieveCandidates(int $clientId,string $message): array
    {
        $queryVector = app(EmbeddingService::class)->generate($message);
        if (!$queryVector) return [];

        $items = KnowledgeBase::where('client_id',$clientId)
            ->whereNotNull('embedding')
            ->get();

        $results = [];

        foreach ($items as $item) {

            $vector = json_decode($item->embedding,true);
            if(!$vector) continue;

            $score = $this->cosine($queryVector,$vector);

            $results[]=[
                'answer'=>$item->answer,
                'score'=>$score
            ];
        }

        usort($results,fn($a,$b)=>$b['score'] <=> $a['score']);

        return array_slice($results,0,$this->candidateLimit);
    }

    protected function cosine(array $a,array $b): float
    {
        $dot=0;$normA=0;$normB=0;

        foreach($a as $i=>$v){
            $dot += $v * ($b[$i] ?? 0);
            $normA += $v*$v;
            $normB += ($b[$i] ?? 0)*($b[$i] ?? 0);
        }

        return $dot/(sqrt($normA)*sqrt($normB)+1e-10);
    }

    /*
    |--------------------------------------------------------------------------
    | GROUNDED AI (RAG AUGMENTED GENERATION)
    |--------------------------------------------------------------------------
    */
    protected function handleGroundedAI(
        int $clientId,
        string $hash,
        string $message,
        array $candidates
    ): string {

        $apiKey = config('services.openai.key');
        if (!$apiKey) {
            return "Please contact our team for assistance.";
        }

        $context = collect($candidates)
            ->pluck('answer')
            ->implode("\n\n");

        $response = Http::withToken($apiKey)
            ->timeout($this->timeout)
            ->retry(2,400)
            ->post('https://api.openai.com/v1/chat/completions',[
                'model'=>'gpt-4o-mini',
                'messages'=>[
                    [
                        'role'=>'system',
                        'content'=>"You are a professional visa consultancy assistant.
Use ONLY the context below if relevant.
If context does not fully answer, provide professional guidance."
                    ],
                    [
                        'role'=>'user',
                        'content'=>"Context:\n".$context."\n\nUser question:\n".$message
                    ]
                ],
                'temperature'=>0.3
            ]);

        if ($response->failed()) {
            return "Please contact our team for assistance.";
        }

        $answer = trim($response->json('choices.0.message.content'));

        return $this->store($clientId,$hash,$answer);
    }

    /*
    |--------------------------------------------------------------------------
    | PURE AI MODE
    |--------------------------------------------------------------------------
    */
    protected function handlePureAI(int $clientId,string $hash,string $message): string
    {
        $apiKey = config('services.openai.key');
        if (!$apiKey) {
            return "Please contact our team for assistance.";
        }

        $response = Http::withToken($apiKey)
            ->timeout($this->timeout)
            ->retry(2,400)
            ->post('https://api.openai.com/v1/chat/completions',[
                'model'=>'gpt-4o-mini',
                'messages'=>[
                    [
                        'role'=>'system',
                        'content'=>'You are a professional Canadian visa consultancy assistant.'
                    ],
                    [
                        'role'=>'user',
                        'content'=>$message
                    ]
                ],
                'temperature'=>0.4
            ]);

        if ($response->failed()) {
            return "Please contact our team for assistance.";
        }

        $answer = trim($response->json('choices.0.message.content'));

        return $this->store($clientId,$hash,$answer);
    }

    protected function store(int $clientId,string $hash,string $answer): string
    {
        AiCache::updateOrCreate(
            ['client_id'=>$clientId,'message_hash'=>$hash],
            ['response'=>$answer]
        );

        return $answer;
    }
}