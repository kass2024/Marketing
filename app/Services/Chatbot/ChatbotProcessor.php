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
    protected bool $debug = true; // set false in production

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

        $this->log('START', compact('clientId','phone','text','messageId'), $requestId);

        // ---------------- VALIDATION ----------------
        if (!$phone || !$text || !$clientId) {
            return null;
        }

        // ---------------- IDEMPOTENCY ----------------
        if ($this->isDuplicate($messageId)) {
            return null;
        }

        // ---------------- RATE LIMIT ----------------
        if (!$this->allowProcessing($clientId, $phone)) {
            return null;
        }

        try {

            return DB::transaction(function () use ($clientId,$phone,$text,$requestId) {

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
                        'status'                => 'bot',
                        'last_activity_at'      => now(),
                        'is_profile_completed'  => 0,
                        'profile_step'          => null
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | STORE INCOMING MESSAGE
                |--------------------------------------------------------------------------
                */

                Message::create([
                    'conversation_id' => $conversation->id,
                    'direction'       => 'incoming',
                    'content'         => $text,
                    'status'          => 'received'
                ]);

                // If human takeover active
                if ($conversation->status === 'human') {
                    return null;
                }

                /*
                |--------------------------------------------------------------------------
                | ONBOARDING FLOW
                |--------------------------------------------------------------------------
                */

                if (!$conversation->is_profile_completed) {

                    $response = $this->handleOnboarding($conversation, $text);

                    $this->storeOutgoing($conversation->id, $response, $requestId);

                    return $response;
                }

                /*
                |--------------------------------------------------------------------------
                | AI RESPONSE
                |--------------------------------------------------------------------------
                */

                $aiResponse = $this->aiEngine->reply(
                    $clientId,
                    $text,
                    $conversation
                );

                if (!$aiResponse || !is_array($aiResponse)) {
                    return $this->errorReply();
                }

                $this->storeOutgoing($conversation->id, $aiResponse, $requestId);

                $conversation->update([
                    'last_activity_at' => now(),
                ]);

                return $aiResponse;
            });

        } catch (\Throwable $e) {

            Log::error('ChatbotProcessor ERROR', [
                'request_id' => $requestId,
                'error'      => $e->getMessage()
            ]);

            return $this->errorReply();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ONBOARDING FLOW
    |--------------------------------------------------------------------------
    */

    protected function handleOnboarding(Conversation $conversation, string $message): array
    {
        $message = trim($message);

        /*
        |--------------------------------------------------------------------------
        | STEP 0: FIRST MESSAGE → ASK FULL NAME
        |--------------------------------------------------------------------------
        */

        if (!$conversation->profile_step) {

            $conversation->update([
                'profile_step' => 'ask_name'
            ]);

            return $this->systemReply(
                "Welcome 👋\nBefore we continue, please provide your *full name*."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | STEP 1: SAVE FULL NAME
        |--------------------------------------------------------------------------
        */

        if ($conversation->profile_step === 'ask_name') {

            if (strlen($message) < 3 || str_word_count($message) < 2) {
                return $this->systemReply(
                    "Please enter your *full name* (first and last name)."
                );
            }

            $conversation->update([
                'customer_name' => ucwords($message),
                'profile_step'  => 'ask_email'
            ]);

            return $this->systemReply(
                "Thank you {$conversation->customer_name} 😊\nNow please provide your *email address*."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | STEP 2: SAVE EMAIL
        |--------------------------------------------------------------------------
        */

        if ($conversation->profile_step === 'ask_email') {

            if (!filter_var($message, FILTER_VALIDATE_EMAIL)) {
                return $this->systemReply(
                    "❌ Please provide a valid email address."
                );
            }

            $conversation->update([
                'customer_email'       => strtolower($message),
                'is_profile_completed' => 1,
                'profile_step'         => 'completed'
            ]);

            return $this->systemReply(
                "✅ Profile completed successfully!\nYou can now ask your questions."
            );
        }

        return $this->systemReply("Please continue.");
    }

    protected function systemReply(string $text): array
    {
        return [
            'text'        => $text,
            'attachments' => [],
            'confidence'  => 1,
            'source'      => 'system'
        ];
    }

    protected function errorReply(): array
    {
        return [
            'text'        => "Sorry, something went wrong. Please try again shortly.",
            'attachments' => [],
            'confidence'  => 0,
            'source'      => 'error'
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | STORE OUTGOING MESSAGE
    |--------------------------------------------------------------------------
    */

    protected function storeOutgoing(int $conversationId, array $response, string $requestId): void
    {
        if (!empty($response['text'])) {

            Message::create([
                'conversation_id' => $conversationId,
                'direction'       => 'outgoing',
                'content'         => $response['text'],
                'status'          => 'pending',
                'meta'            => json_encode([
                    'confidence' => $response['confidence'] ?? null,
                    'source'     => $response['source'] ?? null,
                ])
            ]);
        }

        foreach ($response['attachments'] ?? [] as $attachment) {

            Message::create([
                'conversation_id' => $conversationId,
                'direction'       => 'outgoing',
                'content'         => '[Attachment]',
                'status'          => 'pending',
                'meta'            => json_encode($attachment)
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