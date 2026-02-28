<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\KnowledgeBase;

class AIEngine
{
    public function reply(int $clientId, string $userMessage): string
    {
        // 1️⃣ Try exact or partial match from knowledge base
        $match = KnowledgeBase::where('client_id', $clientId)
            ->whereRaw('LOWER(question) LIKE ?', ['%' . strtolower($userMessage) . '%'])
            ->first();

        if ($match) {
            return $match->answer;
        }

        // 2️⃣ If no match, use OpenAI
        return $this->askOpenAI($clientId, $userMessage);
    }

    protected function askOpenAI(int $clientId, string $userMessage): string
    {
        $knowledge = KnowledgeBase::where('client_id', $clientId)
            ->pluck('question', 'answer')
            ->toArray();

        $context = "";

        foreach ($knowledge as $answer => $question) {
            $context .= "Q: $question\nA: $answer\n\n";
        }

        $prompt = "
You are a professional AI assistant for a company.

Here is the company knowledge base:

$context

User question:
$userMessage

Answer clearly and accurately using the company information.
If the answer is not in the knowledge base, respond professionally.
";

        $response = Http::withToken(config('services.openai.key'))
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a company assistant.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.2
            ]);

        if ($response->failed()) {
            Log::error('OpenAI request failed', [
                'response' => $response->body()
            ]);
            return "Sorry, I’m unable to answer right now.";
        }

        return $response->json('choices.0.message.content');
    }
}