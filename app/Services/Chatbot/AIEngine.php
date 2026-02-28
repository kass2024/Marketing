<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\KnowledgeBase;

class AIEngine
{
    public function reply(int $clientId, string $userMessage): string
    {
        $userMessage = trim($userMessage);

        if ($userMessage === '') {
            return "How can I assist you today?";
        }

        try {
            // 1️⃣ Try knowledge base first
            $local = $this->searchKnowledgeBase($clientId, $userMessage);
            if ($local) {
                return $local;
            }

            // 2️⃣ Fallback to OpenAI
            return $this->askOpenAI($clientId, $userMessage);

        } catch (\Throwable $e) {
            Log::error('AIEngine fatal error', [
                'client_id' => $clientId,
                'error' => $e->getMessage(),
            ]);

            return "Our team will respond shortly.";
        }
    }

    protected function searchKnowledgeBase(int $clientId, string $userMessage): ?string
    {
        $match = KnowledgeBase::where('client_id', $clientId)
            ->where('is_active', true)
            ->whereRaw('LOWER(question) LIKE ?', ['%' . strtolower($userMessage) . '%'])
            ->first();

        if ($match) {
            Log::info('Knowledge match', ['knowledge_id' => $match->id]);
            return $match->answer;
        }

        return null;
    }

    protected function askOpenAI(int $clientId, string $userMessage): string
    {
        $apiKey = config('services.openai.key');

        if (!$apiKey) {
            Log::critical('OpenAI key missing');
            return "Our team will respond shortly.";
        }

        // Limit knowledge context to avoid large prompts
        $knowledge = KnowledgeBase::where('client_id', $clientId)
            ->where('is_active', true)
            ->limit(15)
            ->get(['question', 'answer']);

        $context = '';
        foreach ($knowledge as $item) {
            $context .= "Q: {$item->question}\nA: {$item->answer}\n\n";
        }

        $system = "You are a professional AI assistant for a company.
Use the provided company knowledge when relevant.
Keep answers clear, concise and professional.";

        $user = "Company Knowledge:\n{$context}\n\nUser Question:\n{$userMessage}";

        // 🔥 MODEL FALLBACK STRATEGY
        $models = ['gpt-4o', 'gpt-3.5-turbo'];

        foreach ($models as $model) {

            try {

                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type'  => 'application/json',
                ])
                ->timeout(30)
                ->retry(2, 1000)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                    'temperature' => 0.2,
                    'max_tokens' => 400,
                ]);

                if ($response->successful()) {

                    $content = $response->json('choices.0.message.content');

                    if ($content) {
                        return trim($content);
                    }
                }

                // Log model failure but try fallback
                Log::warning('OpenAI model failed', [
                    'model' => $model,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

            } catch (\Throwable $e) {
                Log::warning('OpenAI request exception', [
                    'model' => $model,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return "Our team will respond shortly.";
    }
}