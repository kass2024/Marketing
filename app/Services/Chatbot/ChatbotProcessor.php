<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
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

        Log::info('AI Mode Processing', [
            'client_id' => $clientId,
            'phone'     => $phone,
            'text'      => $text,
        ]);

        try {

            return DB::transaction(function () use ($clientId, $phone, $text) {

                // Always ensure conversation exists
                $conversation = Conversation::firstOrCreate(
                    [
                        'client_id'    => $clientId,
                        'phone_number' => $phone,
                        'status'       => 'bot',
                    ],
                    [
                        'chatbot_id' => null,
                    ]
                );

                return app(AIEngine::class)
                    ->reply($clientId, $text);
            });

        } catch (\Throwable $e) {

            Log::error('AI processing failed', [
                'error' => $e->getMessage(),
                'client_id' => $clientId,
            ]);

            return "Sorry, I’m having trouble right now.";
        }
    }
}