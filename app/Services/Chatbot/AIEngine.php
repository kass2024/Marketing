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
    protected float $similarityThreshold = 0.65;   // tuned for FAQ systems
    protected float $borderlineThreshold = 0.55;   // rerank zone
    protected int $candidateLimit = 5;
    protected int $memoryLimit = 5;

    public function reply(int $clientId, string $message, $conversation = null): string
    {
        $message = trim($message);

        if (!$message) {
            return "How can we assist you today?";
        }

        if ($this->isGreeting($message)) {
            return "Hello 👋 How can we assist you today regarding study or visa services?";
        }

        $hash = hash('sha256', $clientId . strtolower($message));

        if ($cached = AiCache::where('client_id',$clientId)
            ->where('message_hash',$hash)->first()) {
            return $cached->response;
        }

        $candidates = $this->retrieveCandidates($clientId,$message);

        if (!empty($candidates)) {

            $best = $candidates[0];

            Log::info('Best semantic score', ['score'=>$best['score']]);

            // Strong match
            if ($best['score'] >= $this->similarityThreshold) {
                return $this->store($clientId,$hash,$best['answer']);
            }

            // Borderline match → rerank
            if ($best['score'] >= $this->borderlineThreshold) {

                if ($reranked = $this->rerankWithAI($message,$candidates)) {
                    return $this->store($clientId,$hash,$reranked);
                }
            }
        }

        return $this->groundedFallback($clientId,$hash,$message,$conversation,$candidates ?? []);
    }

    /* ===========================
       Greeting Detection
    ============================ */

    protected function isGreeting(string $message): bool
    {
        $msg = strtolower($message);

        return in_array($msg, [
            'hi','hello','hey',
            'good morning','good afternoon','good evening'
        ]);
    }

    /* ===========================
       Semantic Retrieval
    ============================ */

    protected function retrieveCandidates(int $clientId,string $message): array
    {
        try {

            $queryVector = app(\App\Services\Chatbot\EmbeddingService::class)
                ->generate($message);

            if (!$queryVector) return [];

            $items = KnowledgeBase::where('client_id',$clientId)
                ->whereNotNull('embedding')
                ->get();

            $results = [];

            foreach($items as $item){

                $vector = json_decode($item->embedding,true);
                if(!$vector) continue;

                $score = $this->cosine($queryVector,$vector);

                $results[]=[
                    'question'=>$item->question,
                    'answer'=>$item->answer,
                    'score'=>$score
                ];
            }

            usort($results,fn($a,$b)=>$b['score'] <=> $a['score']);

            return array_slice($results,0,$this->candidateLimit);

        } catch(\Throwable $e){
            Log::error('Semantic retrieval failed',['error'=>$e->getMessage()]);
            return [];
        }
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

    /* ===========================
       AI Reranking (Top 5 only)
    ============================ */

    protected function rerankWithAI(string $message,array $candidates): ?string
    {
        try{

            $apiKey=config('services.openai.key');
            if(!$apiKey) return null;

            $context="";
            foreach($candidates as $i=>$c){
                $context.=($i+1).". {$c['question']}\n";
            }

            $prompt="User Question:\n{$message}\n\nSelect the most relevant number:\n{$context}\nOnly respond with the number.";

            $response=Http::withToken($apiKey)
                ->timeout(30)
                ->post('https://api.openai.com/v1/chat/completions',[
                    'model'=>'gpt-4o-mini',
                    'messages'=>[
                        ['role'=>'system','content'=>'Select best matching question number only.'],
                        ['role'=>'user','content'=>$prompt]
                    ],
                    'temperature'=>0
                ]);

            if($response->failed()) return null;

            $choice=intval(trim($response->json('choices.0.message.content')))-1;

            return $candidates[$choice]['answer'] ?? null;

        }catch(\Throwable $e){
            Log::error('Rerank failed',['error'=>$e->getMessage()]);
            return null;
        }
    }

    /* ===========================
       Strict Grounded Fallback
    ============================ */

    protected function groundedFallback(int $clientId,string $hash,string $message,$conversation,array $candidates): string
    {
        try{

            $apiKey=config('services.openai.key');
            if(!$apiKey){
                return "Please contact our team for accurate assistance.";
            }

            $kbContext="";

            // Only send top 5 candidates to AI
            foreach($candidates as $c){
                $kbContext.="Q: {$c['question']}\nA: {$c['answer']}\n\n";
            }

            $memoryContext="";

            if($conversation){
                $memory=ConversationMemory::where('conversation_id',$conversation->id)
                    ->latest()->take($this->memoryLimit)->get()->reverse();

                foreach($memory as $m){
                    $memoryContext.="{$m->role}: {$m->content}\n";
                }
            }

            $prompt="
You are a professional visa consultancy assistant.

Use ONLY the provided company knowledge below.
If the answer is not found, reply:
'Please contact our team for accurate assistance.'

Conversation Context:
{$memoryContext}

Company Knowledge:
{$kbContext}

User Question:
{$message}
";

            $response=Http::withToken($apiKey)
                ->timeout(30)
                ->post('https://api.openai.com/v1/chat/completions',[
                    'model'=>'gpt-4o-mini',
                    'messages'=>[
                        ['role'=>'system','content'=>'Strictly grounded assistant. No hallucination.'],
                        ['role'=>'user','content'=>$prompt]
                    ],
                    'temperature'=>0.1
                ]);

            if($response->failed()){
                return "Please contact our team for accurate assistance.";
            }

            $answer=trim($response->json('choices.0.message.content'));

            return $this->store($clientId,$hash,$answer);

        }catch(\Throwable $e){
            Log::error('Grounded fallback failed',['error'=>$e->getMessage()]);
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