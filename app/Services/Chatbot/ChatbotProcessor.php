<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Conversation;
use App\Models\Message;

class ChatbotProcessor
{
    public function __construct(
        protected AIEngine $aiEngine
    ) {}

    public function process(array $payload): ?string
    {
        $phone    = $payload['from'] ?? null;
        $text     = trim($payload['text'] ?? '');
        $clientId = $payload['client_id'] ?? null;

        if (!$phone || !$text || !$clientId) {
            Log::warning('Invalid chatbot payload', $payload);
            return null;
        }

        Log::info('AI Processing message', [
            'client_id' => $clientId,
            'phone'     => $phone,
            'text'      => $text,
        ]);

        try {

            return DB::transaction(function () use ($clientId, $phone, $text) {

                // 1️⃣ Ensure conversation exists (no status dependency)
                $conversation = Conversation::firstOrCreate(
                    [
                        'client_id'    => $clientId,
                        'phone_number' => $phone,
                    ],
                    [
                        'chatbot_id' => null,
                        'status'     => 'bot',
                    ]
                );

                // 2️⃣ Save incoming message
                Message::create([
                    'conversation_id' => $conversation->id,
                    'direction'       => 'incoming',
                    'content'         => $text,
                ]);

                // 3️⃣ Generate AI reply
                $reply = $this->aiEngine->reply($clientId, $text);

                if (!$reply) {
                    return null;
                }

                // 4️⃣ Save outgoing message
                Message::create([
                    'conversation_id' => $conversation->id,
                    'direction'       => 'outgoing',
                    'content'         => $reply,
                ]);

                return $reply;
            });

        } catch (\Throwable $e) {

            Log::error('AI processing failed', [
                'error'     => $e->getMessage(),
                'client_id' => $clientId,
                'phone'     => $phone,
            ]);

            return "Sorry, I’m having trouble right now.";
        }
    }
}