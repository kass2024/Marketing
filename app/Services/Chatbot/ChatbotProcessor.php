<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Log;
use App\Models\Chatbot;
use App\Models\Conversation;

class ChatbotProcessor
{
    public function process(array $payload): ?string
    {
        $phone     = $payload['from'] ?? null;
        $text      = strtolower(trim($payload['text'] ?? ''));
        $clientId  = $payload['client_id'] ?? null;

        if (!$phone || !$text || !$clientId) {
            Log::warning('Invalid chatbot payload', $payload);
            return null;
        }

        Log::info('Processing message', [
            'client_id' => $clientId,
            'phone' => $phone,
            'text' => $text,
        ]);

        // 1️⃣ Find existing bot conversation for THIS CLIENT
        $conversation = Conversation::where('client_id', $clientId)
            ->where('phone_number', $phone)
            ->where('status', 'bot')
            ->latest()
            ->first();

        if ($conversation) {
            return app(FlowEngine::class)
                ->continue($conversation, $text);
        }

        return $this->startConversation($clientId, $phone, $text);
    }

    protected function startConversation(
        int $clientId,
        string $phone,
        string $text
    ): ?string
    {
        // Get active chatbot for this client
        $chatbot = Chatbot::where('client_id', $clientId)
            ->where('status', 'active')
            ->first();

        if (!$chatbot) {
            Log::info('No active chatbot for client', [
                'client_id' => $clientId
            ]);
            return null;
        }

        // Check trigger
        $trigger = $chatbot->triggers()
            ->whereRaw('LOWER(keyword) LIKE ?', ["%{$text}%"])
            ->first();

        if (!$trigger) {
            Log::info('No trigger matched', [
                'client_id' => $clientId,
                'text' => $text
            ]);
            return null;
        }

        // Create conversation
        $conversation = Conversation::create([
            'client_id'     => $clientId,
            'chatbot_id'    => $chatbot->id,
            'phone_number'  => $phone,
            'status'        => 'bot',
        ]);

        Log::info('Conversation started', [
            'conversation_id' => $conversation->id
        ]);

        return app(FlowEngine::class)
            ->start($conversation, $chatbot);
    }
}