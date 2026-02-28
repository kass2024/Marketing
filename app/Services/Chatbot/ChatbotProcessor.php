<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Chatbot;
use App\Models\Conversation;

class ChatbotProcessor
{
    public function process(array $payload): ?string
    {
        $phone    = $payload['from'] ?? null;
        $text     = trim($payload['text'] ?? '');
        $clientId = $payload['client_id'] ?? null;

        if (!$phone || !$text || !$clientId) {
            Log::warning('Invalid chatbot payload', $payload);
            return null;
        }

        $text = strtolower($text);

        Log::info('Processing message', [
            'client_id' => $clientId,
            'phone'     => $phone,
            'text'      => $text,
        ]);

        try {
            return DB::transaction(function () use ($clientId, $phone, $text) {

                // 1️⃣ Find existing bot conversation
                $conversation = Conversation::where('client_id', $clientId)
                    ->where('phone_number', $phone)
                    ->where('status', 'bot')
                    ->latest()
                    ->first();

                if ($conversation) {
                    return app(FlowEngine::class)
                        ->continue($conversation, $text);
                }

                // 2️⃣ No conversation → Start new one
                return $this->startConversation($clientId, $phone, $text);
            });

        } catch (\Throwable $e) {

            Log::error('Chatbot processing failed', [
                'error' => $e->getMessage(),
                'client_id' => $clientId,
                'phone' => $phone,
            ]);

            // Never fail silently
            return "Sorry, something went wrong. Please try again.";
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Start New Conversation
    |--------------------------------------------------------------------------
    */
    protected function startConversation(
        int $clientId,
        string $phone,
        string $text
    ): ?string
    {
        // Get active chatbot
        $chatbot = Chatbot::where('client_id', $clientId)
            ->where('status', 'active')
            ->first();

        if (!$chatbot) {
            Log::warning('No active chatbot for client', [
                'client_id' => $clientId
            ]);

            return "Hello 👋 How can we assist you today?";
        }

        // Optional: trigger logic (can be removed if using AI-only mode)
        $trigger = $chatbot->triggers()
            ->whereRaw('LOWER(keyword) LIKE ?', ["%{$text}%"])
            ->first();

        // If no trigger → still start conversation (AI-driven mode)
        if (!$trigger) {
            Log::info('No trigger matched — using AI fallback', [
                'client_id' => $clientId,
                'text' => $text
            ]);
        }

        // Create conversation
        $conversation = Conversation::create([
            'client_id'    => $clientId,
            'chatbot_id'   => $chatbot->id,
            'phone_number' => $phone,
            'status'       => 'bot',
        ]);

        Log::info('Conversation started', [
            'conversation_id' => $conversation->id
        ]);

        /*
        |--------------------------------------------------------------------------
        | If Using Flow Engine
        |--------------------------------------------------------------------------
        */
        if ($trigger) {
            return app(FlowEngine::class)
                ->start($conversation, $chatbot);
        }

        /*
        |--------------------------------------------------------------------------
        | AI MODE (Knowledge + OpenAI)
        |--------------------------------------------------------------------------
        */
        return app(AIEngine::class)
            ->reply($clientId, $text);
    }
}