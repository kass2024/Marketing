<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\KnowledgeBase;
use App\Models\AiCache;

class AIEngine
{
    protected float $highConfidence   = 0.82;
    protected float $mediumConfidence = 0.60;
    protected int   $candidateLimit   = 5;
    protected int   $timeout          = 25;

    /*
    |--------------------------------------------------------------------------
    | MAIN ENTRY
    |--------------------------------------------------------------------------
    */
    public function reply(int $clientId, string $message, $conversation = null): array
    {
        $message = trim($message);

        if ($message === '') {
            return $this->formatResponse(
                "How can we assist you today?",
                [],
                1.0,
                'system'
            );
        }

        $normalized = Str::lower($message);
        $hash = hash('sha256', $clientId . $normalized);

        // ✅ Cache first
        if ($cached = AiCache::where('client_id',$clientId)
            ->where('message_hash',$hash)->first()) {

            return json_decode($cached->response, true);
        }

        if ($this->isGreeting($normalized)) {
            return $this->formatResponse(
                "Hello 👋 How can we assist you regarding study or visa services?",
                [],
                1.0,
                'system'
            );
        }

        // 🔥 Step 1: Retrieve candidates
        $candidates = $this->retrieveCandidates($clientId,$message);

        if (!empty($candidates)) {

            $best = $candidates[0];
            Log::info('Top similarity score: '.$best['score']);

            // HIGH CONFIDENCE → Direct FAQ
            if (
                $best['score'] >= $this->highConfidence &&
                $this->isStrongMatch($message, $best['knowledge']->question)
            ) {
                return $this->store(
                    $clientId,
                    $hash,
                    $this->formatFromKnowledge($best['knowledge'], $best['score'], 'faq')
                );
            }

            // MEDIUM → Grounded AI
            if ($best['score'] >= $this->mediumConfidence) {
                return $this->handleGroundedAI(
                    $clientId,
                    $hash,
                    $message,
                    $candidates
                );
            }
        }

        // LOW → Pure AI
        return $this->handlePureAI($clientId,$hash,$message);
    }

    protected function isGreeting(string $msg): bool
    {
        return in_array($msg,['hi','hello','hey','good morning','good afternoon','good evening']);
    }

    /*
    |--------------------------------------------------------------------------
    | RETRIEVAL (RAG)
    |--------------------------------------------------------------------------
    */
    protected function retrieveCandidates(int $clientId,string $message): array
    {
        $queryVector = app(EmbeddingService::class)->generate($message);
        if (!$queryVector) return [];

        $items = KnowledgeBase::forClient($clientId)
            ->active()
            ->whereNotNull('embedding')
            ->with('attachments')
            ->get();

        $results = [];

        foreach ($items as $item) {

            if (!$item->embedding) continue;

            $score = $this->cosine($queryVector,$item->embedding);

            $results[]=[
                'knowledge'=>$item,
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

    protected function isStrongMatch(string $input, ?string $faqQuestion): bool
    {
        if (!$faqQuestion) return false;

        $inputWords = collect(explode(' ', strtolower($input)));
        $faqWords   = collect(explode(' ', strtolower($faqQuestion)));

        return $inputWords->intersect($faqWords)->count() >= 2;
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
        array $candidates
    ): array {

        $apiKey = config('services.openai.key');
        if (!$apiKey) {
            return $this->formatResponse(
                "Please contact our team for assistance.",
                [],
                0.0,
                'fallback'
            );
        }

        $context = collect($candidates)
            ->pluck('knowledge.answer')
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
Use ONLY the context below if relevant."
                    ],
                    [
                        'role'=>'user',
                        'content'=>"Context:\n".$context."\n\nQuestion:\n".$message
                    ]
                ],
                'temperature'=>0.3
            ]);

        if ($response->failed()) {
            return $this->formatResponse(
                "Please contact our team for assistance.",
                [],
                0.0,
                'fallback'
            );
        }

        $answer = trim($response->json('choices.0.message.content'));

        return $this->store(
            $clientId,
            $hash,
            $this->formatResponse($answer, [], 0.65, 'grounded_ai')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PURE AI
    |--------------------------------------------------------------------------
    */
    protected function handlePureAI(int $clientId,string $hash,string $message): array
    {
        $apiKey = config('services.openai.key');

        if (!$apiKey) {
            return $this->formatResponse(
                "Please contact our team for assistance.",
                [],
                0.0,
                'fallback'
            );
        }

        $response = Http::withToken($apiKey)
            ->timeout($this->timeout)
            ->retry(2,400)
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
                'temperature'=>0.4
            ]);

        if ($response->failed()) {
            return $this->formatResponse(
                "Please contact our team for assistance.",
                [],
                0.0,
                'fallback'
            );
        }

        $answer = trim($response->json('choices.0.message.content'));

        return $this->store(
            $clientId,
            $hash,
            $this->formatResponse($answer, [], 0.5, 'ai')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | FORMATTERS
    |--------------------------------------------------------------------------
    */
    protected function formatFromKnowledge($knowledge, float $confidence, string $source): array
    {
        return [
            'text' => $knowledge->answer,
            'attachments' => $knowledge->attachments->map(function ($att) {
                return [
                    'type' => $att->type,
                    'file_path' => $att->file_path,
                    'url' => $att->url,
                ];
            })->toArray(),
            'confidence' => $confidence,
            'source' => $source
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

    protected function store(int $clientId,string $hash,array $response): array
    {
        AiCache::updateOrCreate(
            ['client_id'=>$clientId,'message_hash'=>$hash],
            ['response'=>json_encode($response)]
        );

        return $response;
    }
}