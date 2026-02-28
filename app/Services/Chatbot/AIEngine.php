<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\KnowledgeBase;

class AIEngine
{
    /**
     * Main reply entry
     */
    public function reply(int $clientId, string $userMessage): string
    {
        $userMessage = trim($userMessage);

        if (empty($userMessage)) {
            return "How can I assist you today?";
        }

        try {
            // 1️⃣ Try local knowledge first
            $localAnswer = $this->searchKnowledgeBase($clientId, $userMessage);

            if ($localAnswer) {
                return $localAnswer;
            }

            // 2️⃣ Fallback to OpenAI
            return $this->askOpenAI($clientId, $userMessage);

        } catch (\Throwable $e) {

            Log::error('AIEngine failed', [
                'client_id' => $clientId,
                'error'     => $e->getMessage(),
            ]);

            return "Sorry, I’m unable to respond right now.";
        }
    }

    /**
     * Search knowledge base (case-insensitive partial match)
     */
    protected function searchKnowledgeBase(int $clientId, string $userMessage): ?string
    {
        $userMessage = strtolower($userMessage);

        $match = KnowledgeBase::where('client_id', $clientId)
            ->where('is_active', true)
            ->whereRaw('LOWER(question) LIKE ?', ['%' . $userMessage . '%'])
            ->first();

        if ($match) {
            Log::info('Knowledge base match found', [
                'knowledge_id' => $match->id,
            ]);

            return $match->answer;
        }

        return null;
    }

    /**
     * Ask OpenAI safely
     */
    protected function askOpenAI(int $clientId, string $userMessage): string
    {
        $apiKey = config('services.openai.key');

        if (empty($apiKey)) {
            Log::critical('OpenAI API key missing');
            return "Our team will get back to you shortly.";
        }

        // Load limited knowledge context (avoid huge prompts)
        $knowledgeItems = KnowledgeBase::where('client_id', $clientId)
            ->where('is_active', true)
            ->limit(20)
            ->get(['question', 'answer']);

        $context = '';

        foreach ($knowledgeItems as $item) {
            $context .= "Q: {$item->question}\nA: {$item->answer}\n\n";
        }

        $systemPrompt = "You are a professional AI assistant for a company.
Use the provided company knowledge to answer accurately.
If unsure, respond professionally and briefly.";

        $userPrompt = "Company Knowledge:\n{$context}\n\nUser Question:\n{$userMessage}";

        try {

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ])
            ->timeout(30)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o', // safest modern model
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.2,
                'max_tokens' => 500,
            ]);

            if ($response->failed()) {

                Log::error('OpenAI API failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                return "Our team will respond shortly.";
            }

            $content = $response->json('choices.0.message.content');

            return $content ?: "Our team will respond shortly.";

        } catch (\Throwable $e) {

            Log::error('OpenAI HTTP exception', [
                'error' => $e->getMessage(),
            ]);

            return "Our team will respond shortly.";
        }
    }
}