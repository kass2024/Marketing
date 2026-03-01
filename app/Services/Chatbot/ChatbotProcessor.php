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

    /*
    |--------------------------------------------------------------------------
    | MAIN PROCESSOR
    |--------------------------------------------------------------------------
    */
    public function process(array $payload): ?array
    {
        $phone     = $payload['from'] ?? null;
        $text      = trim($payload['text'] ?? '');
        $clientId  = $payload['client_id'] ?? null;
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

        // 1️⃣ Idempotency Protection
        if ($this->isDuplicate($messageId)) {
            Log::info('Duplicate message skipped', ['message_id' => $messageId]);
            return null;
        }

        // 2️⃣ Rate Limit Protection
        if (!$this->allowProcessing($clientId, $phone)) {
            Log::warning('Rate limit triggered', compact('clientId','phone'));
            return null;
        }

        try {

            return DB::transaction(function () use ($clientId, $phone, $text) {

                // 3️⃣ Conversation Bootstrap
                $conversation = Conversation::firstOrCreate(
                    [
                        'client_id'    => $clientId,
                        'phone_number' => $phone,
                    ],
                    [
                        'status'            => 'bot',
                        'last_activity_at'  => now(),
                    ]
                );

                // 4️⃣ Save Incoming Message
                $incomingMessage = Message::create([
                    'conversation_id' => $conversation->id,
                    'direction'       => 'incoming',
                    'content'         => $text,
                ]);

                // 5️⃣ Human Takeover Check
                if ($conversation->status === 'human') {
                    Log::info('Conversation under human control', [
                        'conversation_id' => $conversation->id
                    ]);
                    return null;
                }

                // 6️⃣ Generate AI Response
                $aiResponse = $this->aiEngine->reply(
                    $clientId,
                    $text,
                    $conversation
                );

                if (!$aiResponse) {
                    return null;
                }

                // 7️⃣ Persist Outgoing Response
                $this->storeOutgoing($conversation->id, $aiResponse);

                // 8️⃣ Update Conversation Metadata
                $conversation->update([
                    'last_activity_at' => now(),
                ]);

                return $aiResponse;
            });

        } catch (\Throwable $e) {

            Log::error('Chatbot processing failed', [
                'error'     => $e->getMessage(),
                'client_id' => $clientId,
                'phone'     => $phone,
            ]);

            return [
                'text' => "Sorry, I'm experiencing technical issues. Please try again shortly.",
                'attachments' => [],
                'confidence' => 0,
                'source' => 'error'
            ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Store Outgoing Response (Text + Attachments + Metadata)
    |--------------------------------------------------------------------------
    */
    protected function storeOutgoing(int $conversationId, array $response): void
    {
        // Store main text
        if (!empty($response['text'])) {
            Message::create([
                'conversation_id' => $conversationId,
                'direction'       => 'outgoing',
                'content'         => $response['text'],
                'meta'            => json_encode([
                    'confidence' => $response['confidence'] ?? null,
                    'source'     => $response['source'] ?? null,
                ])
            ]);
        }

        // Store attachments if any
        foreach ($response['attachments'] ?? [] as $attachment) {

            Message::create([
                'conversation_id' => $conversationId,
                'direction'       => 'outgoing',
                'content'         => '[Attachment: '.$attachment['type'].']',
                'meta'            => json_encode($attachment),
            ]);

            // 🔥 Here is where you trigger WhatsApp / Twilio / Telegram document sending
            // This layer should call your MessageDispatcher
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
    | Rate Limiting
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