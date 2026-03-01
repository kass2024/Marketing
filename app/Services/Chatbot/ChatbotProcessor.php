<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Models\Conversation;
use App\Models\Message;

class ChatbotProcessor
{
    protected int $rateLimitSeconds = 2;

    public function __construct(
        protected AIEngine $aiEngine
    ) {}

    public function process(array $payload): ?string
    {
        $phone    = $payload['from'] ?? null;
        $text     = trim($payload['text'] ?? '');
        $clientId = $payload['client_id'] ?? null;
        $messageId = $payload['message_id'] ?? Str::uuid()->toString();

        if (!$phone || !$text || !$clientId) {
            Log::warning('Invalid chatbot payload', $payload);
            return null;
        }

        Log::info('Processing incoming message', [
            'client_id' => $clientId,
            'phone'     => $phone,
            'text'      => $text,
        ]);

        // 🔥 1️⃣ Idempotency protection (avoid duplicate webhook delivery)
        if ($this->isDuplicate($messageId)) {
            Log::info('Duplicate message skipped', ['message_id' => $messageId]);
            return null;
        }

        // 🔥 2️⃣ Rate limit protection (avoid spam floods)
        if (!$this->allowProcessing($clientId, $phone)) {
            Log::warning('Rate limit triggered', compact('clientId','phone'));
            return null;
        }

        try {

            return DB::transaction(function () use ($clientId, $phone, $text) {

                // 3️⃣ Conversation bootstrap
                $conversation = Conversation::firstOrCreate(
                    [
                        'client_id'    => $clientId,
                        'phone_number' => $phone,
                    ],
                    [
                        'status'       => 'bot',
                        'last_activity_at' => now(),
                    ]
                );

                // 4️⃣ Save incoming message
                Message::create([
                    'conversation_id' => $conversation->id,
                    'direction'       => 'incoming',
                    'content'         => $text,
                ]);

                // 5️⃣ Check human takeover
                if ($conversation->status === 'human') {
                    Log::info('Conversation under human control', [
                        'conversation_id' => $conversation->id
                    ]);
                    return null;
                }

                // 6️⃣ Generate AI reply
                $reply = $this->aiEngine->reply(
                    $clientId,
                    $text,
                    $conversation
                );

                if (!$reply) {
                    return null;
                }

                // 7️⃣ Save outgoing message
                Message::create([
                    'conversation_id' => $conversation->id,
                    'direction'       => 'outgoing',
                    'content'         => $reply,
                ]);

                // 8️⃣ Update conversation metadata
                $conversation->update([
                    'last_activity_at' => now(),
                ]);

                return $reply;
            });

        } catch (\Throwable $e) {

            Log::error('Chatbot processing failed', [
                'error'     => $e->getMessage(),
                'client_id' => $clientId,
                'phone'     => $phone,
            ]);

            return "Sorry, I'm experiencing technical issues. Please try again shortly.";
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Idempotency Check
    |--------------------------------------------------------------------------
    */
    protected function isDuplicate(string $messageId): bool
    {
        if (Cache::has("msg:$messageId")) {
            return true;
        }

        Cache::put("msg:$messageId", true, now()->addMinutes(10));
        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting (Anti-Spam Protection)
    |--------------------------------------------------------------------------
    */
    protected function allowProcessing(int $clientId, string $phone): bool
    {
        $key = "rate:$clientId:$phone";

        if (Cache::has($key)) {
            return false;
        }

        Cache::put($key, true, now()->addSeconds($this->rateLimitSeconds));
        return true;
    }
}