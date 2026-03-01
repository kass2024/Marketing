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
    protected float $similarityThreshold = 0.78;   // realistic threshold
    protected int $candidateLimit = 5;

    public function reply(int $clientId, string $message, $conversation = null): string
    {
        $message = trim($message);

        if (!$message) {
            return "How can we assist you today?";
        }

        // 1️⃣ Greetings & Short Messages Handling
        if ($this->isGreeting($message)) {
            return "Hello 👋 How can we assist you today regarding study or visa services?";
        }

        // 2️⃣ Cache check
        $hash = hash('sha256', $clientId . strtolower($message));

        if ($cached = AiCache::where('client_id', $clientId)
            ->where('message_hash', $hash)
            ->first()) {
            return $cached->response;
        }

        // 3️⃣ Retrieve candidates (semantic)
        $candidates = $this->retrieveCandidates($clientId, $message);

        if (!empty($candidates)) {

            $best = $candidates[0];

            if ($best['score'] >= $this->similarityThreshold) {
                return $this->store($clientId, $hash, $best['answer']);
            }

            // AI re-ranking if borderline
            $reranked = $this->aiRerank($message, $candidates);

            if ($reranked) {
                return $this->store($clientId, $hash, $reranked);
            }
        }

        // 4️⃣ Controlled AI Fallback
        return $this->groundedAI($clientId, $hash, $message);
    }

    /*
    |--------------------------------------------------------------------------
    | Greeting detection
    |--------------------------------------------------------------------------
    */

    protected function isGreeting(string $message): bool
    {
        $msg = strtolower($message);

        return in_array($msg, [
            'hi','hello','hey','good morning',
            'good afternoon','good evening'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Candidate Retrieval
    |--------------------------------------------------------------------------
    */

    protected function retrieveCandidates(int $clientId, string $message): array
    {
        try {

            $embeddingService = app(EmbeddingService::class);
            $queryVector = $embeddingService->generate($message);

            if (!$queryVector) return [];

            $items = KnowledgeBase::where('client_id', $clientId)
                ->whereNotNull('embedding')
                ->get();

            $results = [];

            foreach ($items as $item) {

                $vector = json_decode($item->embedding, true);
                if (!$vector) continue;

                $score = $this->cosineSimilarity($queryVector, $vector);

                $results[] = [
                    'question' => $item->question,
                    'answer'   => $item->answer,
                    'score'    => $score
                ];
            }

            usort($results, fn($a,$b) => $b['score'] <=> $a['score']);

            return array_slice($results, 0, $this->candidateLimit);

        } catch (\Throwable $e) {
            Log::error('Retrieval failed', ['error'=>$e->getMessage()]);
            return [];
        }
    }

    protected function cosineSimilarity(array $a, array $b): float
    {
        $dot=0;$normA=0;$normB=0;

        foreach($a as $i=>$v){
            $dot += $v * ($b[$i] ?? 0);
            $normA += $v*$v;
            $normB += ($b[$i] ?? 0)*($b[$i] ?? 0);
        }

        return $dot / (sqrt($normA)*sqrt($normB) + 1e-10);
    }

    /*
    |--------------------------------------------------------------------------
    | AI Re-ranking (only for close matches)
    |--------------------------------------------------------------------------
    */

    protected function aiRerank(string $message, array $candidates): ?string
    {
        try {

            $apiKey = config('services.openai.key');
            if (!$apiKey) return null;

            $context = "";

            foreach ($candidates as $i => $c) {
                $context .= ($i+1).". {$c['question']}\n";
            }

            $prompt = "
User Question:
{$message}

Which of these questions is most relevant?

{$context}

Respond with only the number.
";

            $response = Http::withToken($apiKey)
                ->post('https://api.openai.com/v1/chat/completions',[
                    'model'=>'gpt-4o-mini',
                    'messages'=>[
                        ['role'=>'system','content'=>'Select best matching question number only.'],
                        ['role'=>'user','content'=>$prompt]
                    ],
                    'temperature'=>0
                ]);

            if ($response->failed()) return null;

            $choice = intval(trim($response->json('choices.0.message.content'))) - 1;

            if (!isset($candidates[$choice])) return null;

            return $candidates[$choice]['answer'];

        } catch (\Throwable $e) {
            return null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Strict Grounded AI (NO hallucination)
    |--------------------------------------------------------------------------
    */

    protected function groundedAI(int $clientId,string $hash,string $message): string
    {
        try {

            $apiKey = config('services.openai.key');
            if (!$apiKey) {
                return "Please contact our team for accurate assistance.";
            }

            $knowledge = KnowledgeBase::where('client_id',$clientId)
                ->limit(50)
                ->get(['question','answer']);

            $kbText = "";

            foreach($knowledge as $k){
                $kbText .= "Q: {$k->question}\nA: {$k->answer}\n\n";
            }

            $prompt = "
Answer strictly using this company knowledge.
If answer is not found, say:
'Please contact our team for accurate assistance.'

Company Knowledge:
{$kbText}

User Question:
{$message}
";

            $response = Http::withToken($apiKey)
                ->post('https://api.openai.com/v1/chat/completions',[
                    'model'=>'gpt-4o-mini',
                    'messages'=>[
                        ['role'=>'system','content'=>'Strictly grounded assistant.'],
                        ['role'=>'user','content'=>$prompt]
                    ],
                    'temperature'=>0.1
                ]);

            if ($response->failed()) {
                return "Please contact our team for accurate assistance.";
            }

            $answer = trim($response->json('choices.0.message.content'));

            return $this->store($clientId,$hash,$answer);

        } catch (\Throwable $e) {
            return "Please contact our team for accurate assistance.";
        }
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