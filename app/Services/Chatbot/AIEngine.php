<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Models\KnowledgeBase;
use App\Models\AiCache;
use App\Models\ConversationMemory;

class AIEngine
{
    protected float $semanticThreshold = 0.88;     // strict semantic
    protected float $fulltextThreshold = 3.0;      // strict keyword score

    public function reply(int $clientId, string $message, $conversation = null): string
    {
        $message = $this->normalize($message);

        if (!$message) {
            return "How can we assist you today?";
        }

        $hash = hash('sha256', $clientId . $message);

        // 1️⃣ Cache check
        if ($cached = AiCache::where('client_id',$clientId)
            ->where('message_hash',$hash)->first()) {
            return $cached->response;
        }

        // 2️⃣ Semantic search
        if ($semantic = $this->semanticSearch($clientId, $message)) {

            Log::info('Semantic score', ['score'=>$semantic['score']]);

            if ($semantic['score'] >= $this->semanticThreshold) {
                return $this->store($clientId,$hash,$semantic['answer']);
            }
        }

        // 3️⃣ FULLTEXT with score
        if ($keyword = $this->fullTextSearch($clientId, $message)) {

            Log::info('Fulltext score', ['score'=>$keyword['score']]);

            if ($keyword['score'] >= $this->fulltextThreshold) {
                return $this->store($clientId,$hash,$keyword['answer']);
            }
        }

        // 4️⃣ AI fallback (safe & contextual)
        return $this->openAIFallback($clientId,$hash,$message,$conversation);
    }

    protected function normalize(string $text): string
    {
        return trim(Str::lower($text));
    }

    protected function semanticSearch(int $clientId, string $message): ?array
    {
        try {
            $queryVector = app(EmbeddingService::class)
                ->generate($message);

            if (!$queryVector) return null;

            $items = KnowledgeBase::where('client_id',$clientId)
                ->whereNotNull('embedding')
                ->get();

            $bestScore = 0;
            $bestAnswer = null;

            foreach ($items as $item) {

                $vector = is_array($item->embedding)
                    ? $item->embedding
                    : json_decode($item->embedding,true);

                if (!$vector) continue;

                $score = $this->cosine($queryVector,$vector);

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestAnswer = $item->answer;
                }
            }

            if (!$bestAnswer) return null;

            return [
                'score'=>$bestScore,
                'answer'=>$bestAnswer
            ];

        } catch (\Throwable $e) {
            Log::error('Semantic error',['error'=>$e->getMessage()]);
            return null;
        }
    }

    protected function fullTextSearch(int $clientId,string $message): ?array
    {
        try {

            $row = KnowledgeBase::where('client_id',$clientId)
                ->selectRaw("*, MATCH(question,answer) AGAINST(? IN NATURAL LANGUAGE MODE) as score",[$message])
                ->whereRaw("MATCH(question,answer) AGAINST(? IN NATURAL LANGUAGE MODE)",[$message])
                ->orderByDesc('score')
                ->first();

            if (!$row) return null;

            return [
                'score'=>$row->score,
                'answer'=>$row->answer
            ];

        } catch (\Throwable $e) {
            Log::error('Fulltext error',['error'=>$e->getMessage()]);
            return null;
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

        return $dot / (sqrt($normA)*sqrt($normB) + 1e-10);
    }

    protected function openAIFallback(int $clientId,string $hash,string $message,$conversation): string
    {
        $apiKey = config('services.openai.key');

        if (!$apiKey) {
            return "Our team will assist you shortly.";
        }

        $memory = $conversation
            ? ConversationMemory::where('conversation_id',$conversation->id)
                ->latest()->take(5)->get()->reverse()
            : collect();

        $messages = [
            [
                'role'=>'system',
                'content'=>'You are a professional visa consultancy assistant. 
Use only company information. 
Be precise, professional, and do not invent facts.'
            ]
        ];

        foreach($memory as $m){
            $messages[]=[
                'role'=>$m->role,
                'content'=>$m->content
            ];
        }

        $messages[]=['role'=>'user','content'=>$message];

        try {

            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->post('https://api.openai.com/v1/chat/completions',[
                    'model'=>'gpt-4o-mini',
                    'messages'=>$messages,
                    'temperature'=>0.2,
                    'max_tokens'=>500
                ]);

            if($response->failed()){
                Log::error('OpenAI error',['body'=>$response->body()]);
                return "Our team will assist you shortly.";
            }

            $answer = trim($response->json('choices.0.message.content'));

            return $this->store($clientId,$hash,$answer);

        } catch(\Throwable $e){
            Log::error('OpenAI exception',['error'=>$e->getMessage()]);
            return "Our team will assist you shortly.";
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