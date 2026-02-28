<?php

namespace App\Services\Chatbot;

use App\Models\Chatbot;
use App\Models\Conversation;

class ChatbotProcessor
{
    public function process(array $payload): ?string
    {
        $phone = $payload['from'] ?? null;
        $text  = strtolower(trim($payload['text'] ?? ''));

        if (!$phone || !$text) {
            return null;
        }

        // 1️⃣ Find active conversation
        $conversation = Conversation::where('phone_number', $phone)
            ->where('status', 'active')
            ->first();

        if (!$conversation) {
            return $this->startConversation($phone, $text);
        }

        return app(FlowEngine::class)
            ->continue($conversation, $text);
    }

    protected function startConversation(string $phone, string $text): ?string
    {
        // Only one active chatbot for platform
        $chatbot = Chatbot::where('status', 'active')->first();

        if (!$chatbot) {
            return null;
        }

        // Check trigger keywords
        $triggerMatch = $chatbot->triggers()
            ->where('keyword', 'LIKE', "%{$text}%")
            ->exists();

        if (!$triggerMatch) {
            return null;
        }

        $conversation = Conversation::create([
            'client_id'     => 1, // since single business mode
            'chatbot_id'    => $chatbot->id,
            'phone_number'  => $phone,
            'status'        => 'active',
        ]);

        return app(FlowEngine::class)
            ->start($conversation, $chatbot);
    }
}