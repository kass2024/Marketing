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
    protected float $semanticThreshold = 0.60;
    protected int $candidateLimit = 5;
    protected int $timeout = 30;

    public function __construct()
    {
        $this->model = config('services.openai.model', 'gpt-4.1-mini');
    }

    /*
    |--------------------------------------------------------------------------
    | MAIN ENTRY (FAQ FIRST ENTERPRISE FLOW)
    |--------------------------------------------------------------------------
    */

    public function reply(int $clientId, string $message, $conversation = null): array
    {
        $requestId = Str::uuid()->toString();

        Log::info('AIEngine START', compact('requestId','clientId','message'));

        try {

            $message = trim($message);

            if ($message === '') {
                return $this->fallback("How can we assist you today?");
            }

            $normalized = $this->normalize($message);
            $hash = hash('sha256', $clientId . $normalized);

            // ---------------- CACHE ----------------
            if ($cached = AiCache::where('client_id',$clientId)
                ->where('message_hash',$hash)->first()) {

                $decoded = json_decode($cached->response,true);

                if (is_array($decoded)) {
                    Log::info('CACHE HIT', compact('requestId'));
                    return $decoded;
                }
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

            // ---------------- STEP 1: EXACT MATCH ----------------
            $exact = KnowledgeBase::forClient($clientId)
                ->active()
                ->whereRaw('LOWER(question) = ?', [$normalized])
                ->first();

            if ($exact) {
                Log::info('FAQ EXACT MATCH', compact('requestId'));
                return $this->store($clientId,$hash,$this->formatFromKnowledge($exact,1.0,'faq_exact'));
            }

            // ---------------- STEP 2: KEYWORD MATCH ----------------
            $keywordMatch = $this->keywordMatch($clientId,$normalized);

            if ($keywordMatch) {
                Log::info('FAQ KEYWORD MATCH', compact('requestId'));
                return $this->store($clientId,$hash,$this->formatFromKnowledge($keywordMatch,0.9,'faq_keyword'));
            }

            // ---------------- STEP 3: SEMANTIC RAG ----------------
            $semantic = $this->semanticMatch($clientId,$normalized,$requestId);

            if ($semantic) {
                Log::info('FAQ SEMANTIC MATCH', compact('requestId'));
                return $this->store($clientId,$hash,$semantic);
            }

            // ---------------- STEP 4: PURE AI ----------------
            Log::info('PURE AI MODE', compact('requestId'));

            return $this->handlePureAI($clientId,$hash,$message,$requestId);

        } catch (\Throwable $e) {

            Log::error('AIEngine FATAL', [
                'error'=>$e->getMessage(),
                'request_id'=>$requestId
            ]);

            return $this->fallback("Sorry, something went wrong.");
        }
    }

    /*
    |--------------------------------------------------------------------------
    | KEYWORD MATCHING (Deterministic FAQ)
    |--------------------------------------------------------------------------
    */

    protected function keywordMatch(int $clientId,string $input): ?KnowledgeBase
    {
        $words = collect(explode(' ',$input));

        $faqs = KnowledgeBase::forClient($clientId)
            ->active()
            ->get();

        $bestScore = 0;
        $best = null;

        foreach ($faqs as $faq) {

            $faqWords = collect(explode(' ',$this->normalize($faq->question)));

            $score = $words->intersect($faqWords)->count();

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $faq;
            }
        }

        return $bestScore >= 2 ? $best : null;
    }

    /*
    |--------------------------------------------------------------------------
    | SEMANTIC MATCH (Embeddings)
    |--------------------------------------------------------------------------
    */

    protected function semanticMatch(int $clientId,string $message,string $requestId): ?array
    {
        $queryVector = app(EmbeddingService::class)->generate($message);

        if (!$queryVector) {
            Log::warning('Embedding failed', compact('requestId'));
            return null;
        }

        $items = KnowledgeBase::forClient($clientId)
            ->active()
            ->whereNotNull('embedding')
            ->get();

        $bestScore = 0;
        $best = null;

        foreach ($items as $item) {

            if (!is_array($item->embedding)) continue;

            $score = $this->cosine($queryVector,$item->embedding);

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $item;
            }
        }

        Log::info('SEMANTIC SCORE', [
            'request_id'=>$requestId,
            'score'=>$bestScore,
            'question'=>$best->question ?? null
        ]);

        if ($bestScore >= $this->semanticThreshold) {
            return $this->formatFromKnowledge($best,$bestScore,'faq_semantic');
        }

        return null;
    }

    protected function cosine(array $a,array $b): float
    {
        $dot=0;$normA=0;$normB=0;

        foreach($a as $i=>$v){
            $dot += $v*($b[$i]??0);
            $normA += $v*$v;
            $normB += ($b[$i]??0)*($b[$i]??0);
        }

        return $dot/(sqrt($normA)*sqrt($normB)+1e-10);
    }

    /*
    |--------------------------------------------------------------------------
    | PURE AI
    |--------------------------------------------------------------------------
    */

    protected function handlePureAI(int $clientId,string $hash,string $message,string $requestId): array
    {
        $prompt = "You are Visa Consultant Canada assistant.\n\nUser: ".$message;

        $response = Http::withToken(config('services.openai.key'))
            ->timeout($this->timeout)
            ->post('https://api.openai.com/v1/responses',[
                'model'=>$this->model,
                'input'=>$prompt
            ]);

        if ($response->failed()) {
            return $this->fallback();
        }

        $json = $response->json();
        $text = $json['output'][0]['content'][0]['text'] ?? null;

        return $this->store(
            $clientId,
            $hash,
            $this->formatResponse(trim($text ?? ''),[],0.5,'ai')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    protected function normalize(string $text): string
    {
        return trim(preg_replace('/\s+/',' ',Str::lower($text)));
    }

    protected function isGreeting(string $msg): bool
    {
        return in_array($msg,['hi','hello','hey','good morning','good afternoon','good evening']);
    }

    protected function formatResponse(string $text,array $attachments,float $confidence,string $source): array
    {
        return compact('text','attachments','confidence','source');
    }

    protected function formatFromKnowledge($knowledge,float $confidence,string $source): array
    {
        return [
            'text'=>$knowledge->answer,
            'attachments'=>$knowledge->attachments->map(fn($a)=>[
                'type'=>$a->type,
                'url'=>$a->resolved_url ?? $a->url
            ])->toArray(),
            'confidence'=>$confidence,
            'source'=>$source
        ];
    }

    protected function fallback(string $message="Please contact our team."): array
    {
        return [
            'text'=>$message,
            'attachments'=>[],
            'confidence'=>0,
            'source'=>'fallback'
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