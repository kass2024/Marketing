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

    /* --------------------------------------------------------------------------
       THRESHOLDS
    -------------------------------------------------------------------------- */

    protected float $faqThreshold = 0.45;
    protected float $groundThreshold = 0.30;

    protected int $candidateLimit = 5;
    protected int $timeout = 30;

    protected bool $debug = true;

    public function __construct()
    {
        $this->model = config('services.openai.model', 'gpt-4.1-mini');
    }

    /* --------------------------------------------------------------------------
       MAIN ENTRY
    -------------------------------------------------------------------------- */

    public function reply(int $clientId, string $message, $conversation = null): array
    {

        $requestId = Str::uuid()->toString();

        $normalized = $this->normalize($message);

        $hash = hash('sha256', $clientId.$normalized);

        $this->debug("MESSAGE_RECEIVED",[
            'raw'=>$message,
            'normalized'=>$normalized
        ],$requestId);

        /* --------------------------------------------------------------
           HUMAN MODE
        -------------------------------------------------------------- */

        if ($conversation && $conversation->status === 'human') {

            $this->debug("HUMAN_MODE_ACTIVE",[
                'conversation'=>$conversation->id
            ],$requestId);

            return [
                'text'=>'',
                'attachments'=>[],
                'confidence'=>0,
                'source'=>'human_active'
            ];
        }

        try{

            if($normalized===''){
                return $this->fallback("How can we assist you today?");
            }

            /* --------------------------------------------------------------
               USER REQUESTED HUMAN
            -------------------------------------------------------------- */

            if($this->needsHuman($normalized)){

                $this->debug("USER_REQUESTED_AGENT",[], $requestId);

                return $this->handoverToHuman($conversation,$requestId);
            }

            /* --------------------------------------------------------------
               CACHE
            -------------------------------------------------------------- */

            $cached = AiCache::where('client_id',$clientId)
                ->where('message_hash',$hash)
                ->first();

            if($cached){

                $decoded = json_decode($cached->response,true);

                if(is_array($decoded)){

                    $this->debug("CACHE_HIT",[], $requestId);

                    return $decoded;
                }
            }

            /* --------------------------------------------------------------
               GREETING
            -------------------------------------------------------------- */

            if($this->isGreeting($normalized)){

                return $this->formatResponse(
                    "Hello 👋 How can we assist you?",
                    [],
                    1,
                    'system'
                );
            }

            /* --------------------------------------------------------------
               EXACT FAQ
            -------------------------------------------------------------- */

            $exact = KnowledgeBase::forClient($clientId)
                ->active()
                ->whereRaw('LOWER(question)=?',[$normalized])
                ->with('attachments')
                ->first();

            if($exact){

                $this->debug("FAQ_EXACT_MATCH",[
                    'question'=>$exact->question
                ],$requestId);

                return $this->store(
                    $clientId,
                    $hash,
                    $this->formatFromKnowledge($exact,1,'faq_exact')
                );
            }

            /* --------------------------------------------------------------
               KEYWORD SEARCH
            -------------------------------------------------------------- */

            $keywordCandidates = $this->keywordCandidates($clientId,$normalized,$requestId);

            if(!empty($keywordCandidates)){

                $best = $keywordCandidates[0];

                $this->debug("KEYWORD_MATCH",[
                    'question'=>$best['knowledge']->question
                ],$requestId);

                return $this->store(
                    $clientId,
                    $hash,
                    $this->formatFromKnowledge(
                        $best['knowledge'],
                        0.90,
                        'faq_keyword'
                    )
                );
            }

            /* --------------------------------------------------------------
               SEMANTIC SEARCH
            -------------------------------------------------------------- */

            $candidates = $this->retrieveCandidates($clientId,$normalized,$requestId);

            if(!empty($candidates)){

                $best = $candidates[0];

                $this->debug("SEMANTIC_TOP_MATCH",[
                    'score'=>$best['score'],
                    'question'=>$best['knowledge']->question
                ],$requestId);

                if($best['score'] >= $this->faqThreshold){

                    $this->debug("FAQ_SEMANTIC_MODE",[], $requestId);

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

                if($best['score'] >= $this->groundThreshold){

                    $this->debug("GROUNDED_AI_MODE",[], $requestId);

                    return $this->handleGroundedAI(
                        $clientId,
                        $hash,
                        $normalized,
                        $candidates,
                        $requestId
                    );
                }

            }

            /* --------------------------------------------------------------
               PURE AI
            -------------------------------------------------------------- */

            $this->debug("PURE_AI_MODE",[], $requestId);

            $response = $this->handlePureAI(
                $clientId,
                $hash,
                $normalized,
                $requestId
            );

            if(($response['confidence']??1)<0.35){

                return $this->handoverToHuman($conversation,$requestId);
            }

            return $response;

        }catch(\Throwable $e){

            Log::error("AIENGINE_FATAL",[
                'error'=>$e->getMessage(),
                'request_id'=>$requestId
            ]);

            return $this->fallback("Sorry something went wrong.");
        }
    }

    /* --------------------------------------------------------------------------
       KEYWORD SEARCH
    -------------------------------------------------------------------------- */

    protected function keywordCandidates(int $clientId,string $message,string $requestId):array
    {

        $words = explode(' ',$message);

        $query = KnowledgeBase::forClient($clientId)->active();

        foreach($words as $word){

            if(strlen($word)<4) continue;

            $query->orWhere('question','LIKE',"%$word%");
            $query->orWhere('answer','LIKE',"%$word%");
            $query->orWhere('tags','LIKE',"%$word%");
        }

        $items = $query->limit(3)->get();

        $results=[];

        foreach($items as $item){

            $results[]=[
                'knowledge'=>$item,
                'score'=>0.55
            ];
        }

        return $results;
    }

    /* --------------------------------------------------------------------------
       SEMANTIC SEARCH
    -------------------------------------------------------------------------- */

    protected function retrieveCandidates(int $clientId,string $message,string $requestId):array
    {

        $vector = app(EmbeddingService::class)->generate($message);

        if(!$vector){

            $this->debug("EMBEDDING_FAILED",[], $requestId);

            return [];
        }

        $items = KnowledgeBase::forClient($clientId)
            ->active()
            ->whereNotNull('embedding')
            ->with('attachments')
            ->get();

        $results=[];

        foreach($items as $item){

            $embedding = is_array($item->embedding)
                ? $item->embedding
                : json_decode($item->embedding,true);

            if(!$embedding) continue;

            $score = $this->cosine($vector,$embedding);

            $results[]=[
                'knowledge'=>$item,
                'score'=>$score
            ];
        }

        usort($results,fn($a,$b)=>$b['score']<=>$a['score']);

        return array_slice($results,0,$this->candidateLimit);
    }

    protected function cosine(array $a,array $b):float
    {
        $dot=0;$na=0;$nb=0;

        foreach($a as $i=>$v){

            $dot += $v*($b[$i]??0);
            $na += $v*$v;
            $nb += ($b[$i]??0)*($b[$i]??0);
        }

        return $dot/(sqrt($na)*sqrt($nb)+1e-10);
    }

    /* --------------------------------------------------------------------------
       AI MODES
    -------------------------------------------------------------------------- */

    protected function handlePureAI(int $clientId,string $hash,string $message,string $requestId):array
    {

        $prompt="You are a visa consultant assistant.\nUser: ".$message;

        $answer=$this->callOpenAI($prompt,$requestId);

        if(!$answer || strlen($answer)<20){

            return $this->fallback();
        }

        return $this->store(
            $clientId,
            $hash,
            $this->formatResponse(
                $answer,
                [],
                0.5,
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
    ):array{

        $context=collect($candidates)
            ->map(fn($c)=>"Q:{$c['knowledge']->question}\nA:{$c['knowledge']->answer}")
            ->implode("\n\n");

        $prompt="Use FAQ below to answer:\n".$context."\nUser:".$message;

        $answer=$this->callOpenAI($prompt,$requestId);

        return $this->store(
            $clientId,
            $hash,
            $this->formatResponse(
                $answer??"Please contact support.",
                [],
                0.65,
                'grounded_ai'
            )
        );
    }

    /* --------------------------------------------------------------------------
       OPENAI CALL
    -------------------------------------------------------------------------- */

    protected function callOpenAI(string $prompt,string $requestId):?string
    {

        try{

            $response=Http::withToken(config('services.openai.key'))
                ->timeout($this->timeout)
                ->retry(2,500)
                ->post("https://api.openai.com/v1/responses",[
                    "model"=>$this->model,
                    "input"=>$prompt
                ]);

            if($response->failed()){

                Log::error("OPENAI_FAILED",[
                    'status'=>$response->status(),
                    'body'=>$response->body(),
                    'request_id'=>$requestId
                ]);

                return null;
            }

            $json=$response->json();

            return $json['output'][0]['content'][0]['text']
                ?? $json['output_text']
                ?? null;

        }catch(\Throwable $e){

            Log::error("OPENAI_ERROR",[
                'error'=>$e->getMessage()
            ]);

            return null;
        }
    }

    /* --------------------------------------------------------------------------
       HELPERS
    -------------------------------------------------------------------------- */

    protected function normalize(string $text):string
    {

        $text = Str::lower($text);

        $text = preg_replace('/[^a-z0-9\s]/','',$text);

        $text = preg_replace('/\s+/',' ',$text);

        return trim($text);
    }

    protected function isGreeting(string $msg):bool
    {
        return preg_match('/\b(hi|hello|hey|good morning|good afternoon)\b/',$msg);
    }

    protected function needsHuman(string $message,?float $confidence=null):bool
    {

        $keywords=['human','agent','support','representative'];

        foreach($keywords as $word){

            if(str_contains($message,$word)) return true;
        }

        if($confidence!==null && $confidence<0.35) return true;

        return false;
    }

    protected function handoverToHuman($conversation,string $requestId):array
    {

        if($conversation){

            $conversation->update([
                'status'=>'human',
                'escalation_reason'=>'ai_escalation',
                'last_activity_at'=>now()
            ]);
        }

        return [
            'text'=>"I'm connecting you to a human agent 👩‍💻 Please wait.",
            'attachments'=>[],
            'confidence'=>1,
            'source'=>'handover'
        ];
    }

    protected function formatResponse(string $text,array $attachments,float $confidence,string $source):array
    {
        return compact('text','attachments','confidence','source');
    }

    protected function formatFromKnowledge($knowledge,float $confidence,string $source):array
    {
        return [
            'text'=>$knowledge->answer ?? '',
            'attachments'=>[],
            'confidence'=>$confidence,
            'source'=>$source
        ];
    }

    protected function fallback(string $message="Please contact support."):array
    {
        return [
            'text'=>$message,
            'attachments'=>[],
            'confidence'=>0,
            'source'=>'fallback'
        ];
    }

    protected function store(int $clientId,string $hash,array $response):array
    {

        AiCache::updateOrCreate(
            ['client_id'=>$clientId,'message_hash'=>$hash],
            ['response'=>json_encode($response)]
        );

        return $response;
    }

    protected function debug(string $stage,array $data,string $requestId):void
    {

        if(!$this->debug) return;

        Log::info("AIENGINE_DEBUG",[
            'stage'=>$stage,
            'request_id'=>$requestId,
            'data'=>$data
        ]);
    }

}