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

    protected float $faqThreshold = 0.55;
    protected float $groundThreshold = 0.35;

    protected int $candidateLimit = 5;
    protected int $timeout = 30;

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
        $hash = hash('sha256', $clientId.$normalized);

        $this->log('MESSAGE_RECEIVED', [
            'conversation_id'=>$conversation?->id,
            'message'=>$normalized
        ],$requestId);

        if ($conversation && $conversation->status === 'human') {
            return [
                'text'=>'',
                'attachments'=>[],
                'confidence'=>0,
                'source'=>'human_active'
            ];
        }

        try {

            if ($normalized === '') {
                return $this->fallback("How can we assist you today?");
            }

            if ($this->needsHuman($normalized)) {
                return $this->handoverToHuman($conversation,$requestId);
            }

            /*
            |--------------------------------------------------------------------------
            | CACHE
            |--------------------------------------------------------------------------
            */

            $cached = AiCache::where('client_id',$clientId)
                ->where('message_hash',$hash)
                ->first();

            if($cached){
                $decoded=json_decode($cached->response,true);
                if(is_array($decoded)){
                    $this->log('CACHE_HIT',[],$requestId);
                    return $decoded;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | GREETING
            |--------------------------------------------------------------------------
            */

            if($this->isGreeting($normalized)){
                return $this->formatResponse(
                    "Hello 👋 How can we assist you?",
                    [],
                    1,
                    'system'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | EXACT FAQ
            |--------------------------------------------------------------------------
            */

            $exact = KnowledgeBase::forClient($clientId)
                ->active()
                ->whereRaw('LOWER(question)=?',[$normalized])
                ->with('attachments')
                ->first();

            if($exact){

                $this->log('FAQ_EXACT_MATCH',[
                    'question'=>$exact->question
                ],$requestId);

                return $this->store(
                    $clientId,
                    $hash,
                    $this->formatFromKnowledge($exact,1,'faq_exact')
                );
            }

            /*
            |--------------------------------------------------------------------------
            | SEMANTIC SEARCH
            |--------------------------------------------------------------------------
            */

            $candidates=$this->retrieveCandidates($clientId,$normalized,$requestId);

            if(!empty($candidates)){

                $best=$candidates[0];

                $this->log('TOP_CANDIDATE',[
                    'score'=>$best['score'],
                    'question'=>$best['knowledge']->question
                ],$requestId);

                if($best['score'] >= $this->faqThreshold){

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

                    return $this->handleGroundedAI(
                        $clientId,
                        $hash,
                        $normalized,
                        $candidates,
                        $requestId
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | PURE AI
            |--------------------------------------------------------------------------
            */

            $response=$this->handlePureAI(
                $clientId,
                $hash,
                $normalized,
                $requestId
            );

            if(($response['confidence'] ?? 1) < 0.35){
                return $this->handoverToHuman($conversation,$requestId);
            }

            return $response;

        }catch(\Throwable $e){

            Log::error('AIENGINE_FATAL',[
                'error'=>$e->getMessage(),
                'request_id'=>$requestId
            ]);

            return $this->fallback("Sorry something went wrong.");
        }
    }

    /*
    |--------------------------------------------------------------------------
    | RETRIEVAL
    |--------------------------------------------------------------------------
    */

    protected function retrieveCandidates(int $clientId,string $message,string $requestId):array
    {
        $vector=app(EmbeddingService::class)->generate($message);

        if(!$vector){
            $this->log('EMBEDDING_FAILED',[],$requestId);
            return [];
        }

        $items=KnowledgeBase::forClient($clientId)
            ->active()
            ->whereNotNull('embedding')
            ->with('attachments')
            ->get();

        $results=[];

        foreach($items as $item){

            $embedding=$item->embedding;

            if(is_string($embedding)){
                $embedding=json_decode($embedding,true);
            }

            if(!is_array($embedding)){
                continue;
            }

            $score=$this->cosine($vector,$embedding);

            /*
            |--------------------------------------------------------------------------
            | KEYWORD BOOST
            |--------------------------------------------------------------------------
            */

            $question=Str::lower($item->question);
            $words=explode(' ',$message);

            $boost=0;

            foreach($words as $w){

                $w=trim($w);

                if(strlen($w) < 4){
                    continue;
                }

                if(str_contains($question,$w)){
                    $boost += 0.05;
                }
            }

            $boost=min($boost,0.25);

            $score+=$boost;

            $results[]=[
                'knowledge'=>$item,
                'score'=>$score
            ];
        }

        usort($results,fn($a,$b)=>$b['score'] <=> $a['score']);

        return array_slice($results,0,$this->candidateLimit);
    }

    protected function cosine(array $a,array $b):float
    {
        $dot=0;$na=0;$nb=0;

        foreach($a as $i=>$v){
            $dot+=$v*($b[$i] ?? 0);
            $na+=$v*$v;
            $nb+=($b[$i] ?? 0)*($b[$i] ?? 0);
        }

        return $dot/(sqrt($na)*sqrt($nb)+1e-10);
    }

    /*
    |--------------------------------------------------------------------------
    | AI MODES
    |--------------------------------------------------------------------------
    */

    protected function handlePureAI(int $clientId,string $hash,string $message,string $requestId):array
    {
        $prompt="You are a professional visa consultant assistant.\n\nUser: ".$message;

        $answer=$this->callOpenAI($prompt,$requestId);

        return $this->store(
            $clientId,
            $hash,
            $this->formatResponse(
                $answer ?? "Please contact support.",
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
        ->map(fn($c)=>"Question: {$c['knowledge']->question}\nAnswer: {$c['knowledge']->answer}")
        ->implode("\n\n");

        $prompt="
Use the knowledge base below to answer the user.

$context

User question:
$message

Provide a helpful answer using the knowledge above.
";

        $answer=$this->callOpenAI($prompt,$requestId);

        return $this->store(
            $clientId,
            $hash,
            $this->formatResponse(
                $answer ?? "Please contact support.",
                [],
                0.65,
                'grounded_ai'
            )
        );
    }

    protected function callOpenAI(string $prompt,string $requestId):?string
    {
        try{

            $response=Http::withToken(config('services.openai.key'))
                ->timeout($this->timeout)
                ->retry(2,500)
                ->post('https://api.openai.com/v1/responses',[
                    'model'=>$this->model,
                    'input'=>$prompt
                ]);

            if($response->failed()){
                Log::error('OPENAI_FAILED',[
                    'status'=>$response->status(),
                    'body'=>$response->body()
                ]);
                return null;
            }

            $json=$response->json();

            return $json['output'][0]['content'][0]['text'] ?? null;

        }catch(\Throwable $e){

            Log::error('OPENAI_ERROR',[
                'error'=>$e->getMessage()
            ]);

            return null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    protected function normalize(string $text):string
    {
        $text=Str::lower($text);
        $text=preg_replace('/[^\w\s]/','',$text);
        return trim(preg_replace('/\s+/',' ',$text));
    }

    protected function isGreeting(string $msg):bool
    {
        return in_array($msg,['hi','hello','hey','good morning','good evening']);
    }

    protected function needsHuman(string $message,?float $confidence=null):bool
    {
        $keywords=['human','agent','support','representative'];

        foreach($keywords as $word){
            if(str_contains($message,$word)){
                return true;
            }
        }

        if($confidence !== null && $confidence < 0.35){
            return true;
        }

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

    protected function log(string $title,array $data,string $requestId):void
    {
        if($this->debug){
            Log::info("AIEngine ".$title,array_merge([
                'request_id'=>$requestId
            ],$data));
        }
    }
}