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
    protected bool $debug = true; // turn OFF in production if needed

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

        $this->log('START', compact(
            'clientId','phone','text','messageId'
        ), $requestId);

        // ---------------- VALIDATION ----------------
        if (!$phone || !$text || !$clientId) {
            $this->log('INVALID PAYLOAD', $payload, $requestId);
            return null;
        }

        // ---------------- IDEMPOTENCY ----------------
        if ($this->isDuplicate($messageId)) {
            $this->log('DUPLICATE MESSAGE BLOCKED', [
                'message_id' => $messageId
            ], $requestId);
            return null;
        }

        // ---------------- RATE LIMIT ----------------
        if (!$this->allowProcessing($clientId, $phone)) {
            $this->log('RATE LIMITED', [
                'client_id' => $clientId,
                'phone'     => $phone
            ], $requestId);
            return null;
        }

        try {

            return DB::transaction(function () use (
                $clientId,
                $phone,
                $text,
                $requestId
            ) {

                /*
                |--------------------------------------------------------------------------
                | RESOLVE CONVERSATION
                |--------------------------------------------------------------------------
                */

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

                $this->log('CONVERSATION RESOLVED', [
                    'conversation_id' => $conversation->id,
                    'status'          => $conversation->status
                ], $requestId);

                /*
                |--------------------------------------------------------------------------
                | STORE INCOMING MESSAGE
                |--------------------------------------------------------------------------
                */

                $incoming = Message::create([
                    'conversation_id' => $conversation->id,
                    'direction'       => 'incoming',
                    'content'         => $text,
                    'status'          => 'received'
                ]);

                $this->log('INCOMING STORED', [
                    'message_id' => $incoming->id
                ], $requestId);

                /*
                |--------------------------------------------------------------------------
                | HUMAN TAKEOVER CHECK
                |--------------------------------------------------------------------------
                */

                if ($conversation->status === 'human') {

                    $this->log('HUMAN MODE ACTIVE', [
                        'conversation_id' => $conversation->id
                    ], $requestId);

                    return null;
                }

                /*
                |--------------------------------------------------------------------------
                | CALL AI ENGINE
                |--------------------------------------------------------------------------
                */

                $this->log('CALLING AI ENGINE', [], $requestId);

                $aiResponse = $this->aiEngine->reply(
                    $clientId,
                    $text,
                    $conversation
                );

                if (!$aiResponse || !is_array($aiResponse)) {

                    $this->log('AI RETURNED EMPTY', [], $requestId);

                    return [
                        'text'        => "Sorry, I’m having trouble right now. Please try again shortly.",
                        'attachments' => [],
                        'confidence'  => 0,
                        'source'      => 'error'
                    ];
                }

                $this->log('AI RESPONSE RECEIVED', [
                    'preview' => substr($aiResponse['text'] ?? '', 0, 150),
                    'source'  => $aiResponse['source'] ?? null
                ], $requestId);

                /*
                |--------------------------------------------------------------------------
                | STORE OUTGOING RESPONSE
                |--------------------------------------------------------------------------
                */

                $this->storeOutgoing(
                    conversationId: $conversation->id,
                    response: $aiResponse,
                    requestId: $requestId
                );

                /*
                |--------------------------------------------------------------------------
                | UPDATE CONVERSATION
                |--------------------------------------------------------------------------
                */

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
                'text'        => "Technical issue occurred. Please try again.",
                'attachments' => [],
                'confidence'  => 0,
                'source'      => 'error'
            ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | STORE OUTGOING RESPONSE
    |--------------------------------------------------------------------------
    */

    protected function storeOutgoing(
        int $conversationId,
        array $response,
        string $requestId
    ): void {

        if (!empty($response['text'])) {

            $message = Message::create([
                'conversation_id' => $conversationId,
                'direction'       => 'outgoing',
                'content'         => $response['text'],
                'status'          => 'pending',
                'meta'            => json_encode([
                    'confidence' => $response['confidence'] ?? null,
                    'source'     => $response['source'] ?? null,
                ])
            ]);

            $this->log('OUTGOING TEXT STORED', [
                'message_id' => $message->id
            ], $requestId);
        }

        foreach ($response['attachments'] ?? [] as $attachment) {

            $att = Message::create([
                'conversation_id' => $conversationId,
                'direction'       => 'outgoing',
                'content'         => '[Attachment]',
                'status'          => 'pending',
                'meta'            => json_encode($attachment)
            ]);

            $this->log('OUTGOING ATTACHMENT STORED', [
                'message_id' => $att->id,
                'type'       => $attachment['type'] ?? null
            ], $requestId);
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

    /*
    |--------------------------------------------------------------------------
    | LOGGER
    |--------------------------------------------------------------------------
    */

    protected function log(string $title, array $data, string $requestId): void
    {
        if ($this->debug) {
            Log::info("ChatbotProcessor {$title}", array_merge(
                ['request_id' => $requestId],
                $data
            ));
        }
    }
}