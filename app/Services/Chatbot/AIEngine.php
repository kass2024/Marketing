<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\KnowledgeBase;
use App\Models\AiCache;
use App\Models\ConversationMemory;

class AIEngine
{
    protected float $strongThreshold = 0.75;
    protected int $candidateLimit = 5;
    protected int $timeout = 30;

    public function reply(int $clientId, string $message, $conversation = null): string
    {
        $message = trim($message);

        if ($message === '') {
            return "How can we assist you today?";
        }

        $normalized = Str::lower($message);
        $hash = hash('sha256', $clientId . $normalized);

        if ($cached = AiCache::where('client_id',$clientId)
            ->where('message_hash',$hash)->first()) {
            return $cached->response;
        }

        if ($this->isGreeting($normalized)) {
            return "Hello 👋 How can we assist you today regarding study or visa services?";
        }

        // 🔥 STEP 1 — INTENT CLASSIFICATION
        $intent = $this->classifyIntent($message);

        if ($intent === 'faq') {
            return $this->handleFaqIntent($clientId,$hash,$message);
        }

        // Otherwise advisory
        return $this->handleAdvisoryIntent($clientId,$hash,$message,$conversation);
    }

    protected function isGreeting(string $msg): bool
    {
        return in_array($msg,['hi','hello','hey','good morning','good afternoon','good evening']);
    }

    /*
    |--------------------------------------------------------------------------
    | INTENT CLASSIFIER
    |--------------------------------------------------------------------------
    */
    protected function classifyIntent(string $message): string
    {
        $apiKey = config('services.openai.key');
        if (!$apiKey) return 'faq';

        $prompt = "
Classify the user question into one of these categories:
1. faq (specific factual company question)
2. advisory (general guidance or migration advice)

User question:
{$message}

Respond with only: faq or advisory.
";

        try {

            $response = Http::withToken($apiKey)
                ->timeout(15)
                ->post('https://api.openai.com/v1/chat/completions',[
                    'model'=>'gpt-4o-mini',
                    'messages'=>[
                        ['role'=>'system','content'=>'Intent classifier.'],
                        ['role'=>'user','content'=>$prompt]
                    ],
                    'temperature'=>0
                ]);

            if ($response->failed()) return 'faq';

            return trim($response->json('choices.0.message.content'));

        } catch (\Throwable $e) {
            return 'faq';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | FAQ HANDLER (RAG)
    |--------------------------------------------------------------------------
    */
    protected function handleFaqIntent(int $clientId,string $hash,string $message): string
    {
        $candidates = $this->retrieveCandidates($clientId,$message);

        if (!empty($candidates)) {

            $best = $candidates[0];

            if ($best['score'] >= $this->strongThreshold) {
                return $this->store($clientId,$hash,$best['answer']);
            }
        }

        return "Please contact our team for accurate assistance.";
    }

    protected function retrieveCandidates(int $clientId,string $message): array
    {
        $queryVector = app(\App\Services\Chatbot\EmbeddingService::class)
            ->generate($message);

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
    | ADVISORY HANDLER (AI MODE)
    |--------------------------------------------------------------------------
    */
    protected function handleAdvisoryIntent(int $clientId,string $hash,string $message,$conversation): string
    {
        $apiKey = config('services.openai.key');
        if (!$apiKey) {
            return "Please contact our team for accurate assistance.";
        }

        $response = Http::withToken($apiKey)
            ->timeout($this->timeout)
            ->post('https://api.openai.com/v1/chat/completions',[
                'model'=>'gpt-4o-mini',
                'messages'=>[
                    [
                        'role'=>'system',
                        'content'=>'You are a professional visa consultancy assistant.'
                    ],
                    [
                        'role'=>'user',
                        'content'=>$message
                    ]
                ],
                'temperature'=>0.3
            ]);

        if ($response->failed()) {
            return "Please contact our team for accurate assistance.";
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