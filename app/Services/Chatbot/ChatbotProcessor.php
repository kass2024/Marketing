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
    | MAIN ENTRY
    |--------------------------------------------------------------------------
    */

    public function process(array $payload): ?array
    {
        $requestId = Str::uuid()->toString();

        $phone     = $payload['from'] ?? null;
        $text      = trim($payload['text'] ?? '');
        $clientId  = $payload['client_id'] ?? null;
        $messageId = $payload['message_id'] ?? Str::uuid()->toString();

        Log::info('ChatbotProcessor START', [
            'request_id' => $requestId,
            'client_id'  => $clientId,
            'phone'      => $phone,
            'text'       => $text,
            'message_id' => $messageId
        ]);

        // ---------------- VALIDATION ----------------
        if (!$phone || !$text || !$clientId) {

            Log::warning('ChatbotProcessor INVALID PAYLOAD', [
                'request_id' => $requestId,
                'payload'    => $payload
            ]);

            return null;
        }

        // ---------------- IDEMPOTENCY ----------------
        if ($this->isDuplicate($messageId)) {

            Log::info('ChatbotProcessor DUPLICATE MESSAGE', [
                'request_id' => $requestId,
                'message_id' => $messageId
            ]);

            return null;
        }

        // ---------------- RATE LIMIT ----------------
        if (!$this->allowProcessing($clientId, $phone)) {

            Log::warning('ChatbotProcessor RATE LIMITED', [
                'request_id' => $requestId,
                'client_id'  => $clientId,
                'phone'      => $phone
            ]);

            return null;
        }

        try {

            return DB::transaction(function () use (
                $clientId,
                $phone,
                $text,
                $requestId
            ) {

                // ---------------- CONVERSATION ----------------
                $conversation = Conversation::firstOrCreate(
                    [
                        'client_id'    => $clientId,
                        'phone_number' => $phone,
                    ],
                    [
                        'status'           => 'bot',
                        'last_activity_at' => now(),
                    ]
                );

                Log::info('Conversation resolved', [
                    'request_id'       => $requestId,
                    'conversation_id'  => $conversation->id,
                    'status'           => $conversation->status
                ]);

                // ---------------- SAVE INCOMING ----------------
                $incoming = Message::create([
                    'conversation_id' => $conversation->id,
                    'direction'       => 'incoming',
                    'content'         => $text,
                    'status'          => 'received'
                ]);

                Log::info('Incoming message stored', [
                    'request_id' => $requestId,
                    'message_id' => $incoming->id
                ]);

                // ---------------- HUMAN TAKEOVER ----------------
                if ($conversation->status === 'human') {

                    Log::info('Conversation under human control', [
                        'request_id' => $requestId,
                        'conversation_id' => $conversation->id
                    ]);

                    return null;
                }

                // ---------------- AI CALL ----------------
                Log::info('Calling AIEngine', [
                    'request_id' => $requestId
                ]);

                $aiResponse = $this->aiEngine->reply(
                    $clientId,
                    $text,
                    $conversation
                );

                if (!$aiResponse || !is_array($aiResponse)) {

                    Log::warning('AIEngine returned empty response', [
                        'request_id' => $requestId
                    ]);

                    return null;
                }

                Log::info('AIEngine RESPONSE RECEIVED', [
                    'request_id' => $requestId,
                    'response_preview' => substr($aiResponse['text'] ?? '', 0, 120)
                ]);

                // ---------------- STORE OUTGOING ----------------
                $this->storeOutgoing(
                    conversationId: $conversation->id,
                    response: $aiResponse,
                    requestId: $requestId
                );

                // ---------------- UPDATE CONVERSATION ----------------
                $conversation->update([
                    'last_activity_at' => now(),
                ]);

                return $aiResponse;
            });

        } catch (\Throwable $e) {

            Log::error('ChatbotProcessor FATAL', [
                'request_id' => $requestId,
                'error'      => $e->getMessage()
            ]);

            return [
                'text'        => "Sorry, I'm experiencing technical issues. Please try again shortly.",
                'attachments' => [],
                'confidence'  => 0,
                'source'      => 'error'
            ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | STORE OUTGOING (TEXT + ATTACHMENTS)
    |--------------------------------------------------------------------------
    */

    protected function storeOutgoing(
        int $conversationId,
        array $response,
        string $requestId
    ): void {

        // ---------- MAIN TEXT ----------
        if (!empty($response['text'])) {

            $out = Message::create([
                'conversation_id' => $conversationId,
                'direction'       => 'outgoing',
                'content'         => $response['text'],
                'status'          => 'pending',
                'meta'            => json_encode([
                    'confidence' => $response['confidence'] ?? null,
                    'source'     => $response['source'] ?? null,
                ])
            ]);

            Log::info('Outgoing text stored', [
                'request_id' => $requestId,
                'message_id' => $out->id
            ]);
        }

        // ---------- ATTACHMENTS ----------
        foreach ($response['attachments'] ?? [] as $attachment) {

            $att = Message::create([
                'conversation_id' => $conversationId,
                'direction'       => 'outgoing',
                'content'         => '[Attachment: ' . ($attachment['type'] ?? 'unknown') . ']',
                'status'          => 'pending',
                'meta'            => json_encode($attachment)
            ]);

            Log::info('Outgoing attachment stored', [
                'request_id' => $requestId,
                'message_id' => $att->id,
                'type'       => $attachment['type'] ?? null
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | IDEMPOTENCY
    |--------------------------------------------------------------------------
    */

    protected function isDuplicate(string $messageId): bool
    {
        $key = "msg:$messageId";

        if (Cache::has($key)) {
            return true;
        }

        Cache::put($key, true, now()->addMinutes(10));

        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | RATE LIMIT
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