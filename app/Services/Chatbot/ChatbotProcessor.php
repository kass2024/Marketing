<?php

namespace App\Services\Chatbot;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\HumanHandoffTimeoutService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChatbotProcessor
{
    protected int $rateLimitSeconds = 2;

    protected bool $debug = true; // set false in strict production

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

        $phone = $payload['from'] ?? null;
        $text = trim($payload['text'] ?? '');
        $clientId = $payload['client_id'] ?? null;
        $messageId = $payload['message_id'] ?? Str::uuid()->toString();
        $inboundDisplayContent = isset($payload['inbound_display_content']) ? trim((string) $payload['inbound_display_content']) : null;

        /*
        |--------------------------------------------------------------------------
        | Ads Attribution (Enterprise)
        |--------------------------------------------------------------------------
        */
        $metaCampaignId = $payload['meta_campaign_id'] ?? null;
        $metaAdsetId = $payload['meta_adset_id'] ?? null;
        $metaAdId = $payload['meta_ad_id'] ?? null;
        $source = $payload['source'] ?? 'organic';

        $this->log('START', compact(
            'clientId', 'phone', 'text', 'messageId'
        ), $requestId);

        if (! $phone || ! $text || ! $clientId) {
            $this->log('INVALID PAYLOAD', $payload, $requestId);

            return null;
        }

        if ($this->isDuplicate($messageId)) {
            $this->log('DUPLICATE BLOCKED', ['message_id' => $messageId], $requestId);

            return null;
        }

        if (! $this->allowProcessing($clientId, $phone)) {
            $this->log('RATE LIMITED', compact('clientId', 'phone'), $requestId);

            return null;
        }

        try {

            return DB::transaction(function () use (
                $clientId,
                $phone,
                $text,
                $metaCampaignId,
                $metaAdsetId,
                $metaAdId,
                $source,
                $requestId,
                $payload,
                $inboundDisplayContent
            ) {

                /*
                |--------------------------------------------------------------------------
                | RESOLVE CONVERSATION
                |--------------------------------------------------------------------------
                */

                $conversation = Conversation::firstOrCreate(
                    [
                        'client_id' => $clientId,
                        'phone_number' => $phone,
                    ],
                    [
                        'status' => 'bot',
                        'last_activity_at' => now(),
                        'is_profile_completed' => 0,
                        'profile_step' => null,
                        'meta_campaign_id' => $metaCampaignId,
                        'meta_adset_id' => $metaAdsetId,
                        'meta_ad_id' => $metaAdId,
                        'source' => $source,
                        'first_contact_at' => now(),
                    ]
                );

                $this->log('CONVERSATION RESOLVED', [
                    'conversation_id' => $conversation->id,
                    'status' => $conversation->status,
                    'source' => $conversation->source,
                ], $requestId);

                /*
                |--------------------------------------------------------------------------
                | FIRST-TOUCH ATTRIBUTION PROTECTION
                |--------------------------------------------------------------------------
                | If conversation was organic but now paid,
                | upgrade it ONCE.
                */

                if (
                    $source === 'paid' &&
                    $conversation->source === 'organic'
                ) {
                    $conversation->update([
                        'meta_campaign_id' => $metaCampaignId,
                        'meta_adset_id' => $metaAdsetId,
                        'meta_ad_id' => $metaAdId,
                        'source' => 'paid',
                    ]);

                    $this->log('ATTRIBUTION UPDATED', [
                        'conversation_id' => $conversation->id,
                        'campaign_id' => $metaCampaignId,
                    ], $requestId);
                }

                /*
                |--------------------------------------------------------------------------
                | STORE INCOMING MESSAGE
                |--------------------------------------------------------------------------
                */

                $incomingRow = [
                    'conversation_id' => $conversation->id,
                    'direction' => 'incoming',
                    'content' => $inboundDisplayContent !== null && $inboundDisplayContent !== '' ? $inboundDisplayContent : $text,
                    'status' => 'received',
                ];

                if (! empty($payload['inbound_media_url'])) {
                    $incomingRow['type'] = 'media';
                    $incomingRow['media_type'] = $payload['inbound_media_type'] ?? 'audio';
                    $incomingRow['media_url'] = $payload['inbound_media_url'];
                    $incomingRow['filename'] = $payload['inbound_filename'] ?? 'Voice note';
                }

                $incoming = Message::create($incomingRow);

                $this->log('INCOMING STORED', [
                    'message_id' => $incoming->id,
                ], $requestId);

                app(HumanHandoffTimeoutService::class)->checkAndRelease($conversation->fresh());
                $conversation = $conversation->fresh();

                /*
                |--------------------------------------------------------------------------
                | HUMAN TAKEOVER CHECK
                |--------------------------------------------------------------------------
                */

                if ($conversation->isEscalated()) {
                    $this->log('HUMAN OR ESCALATED — skip bot reply', [], $requestId);

                    return null;
                }

                /*
                |--------------------------------------------------------------------------
                | MANDATORY PROFILE ONBOARDING
                |--------------------------------------------------------------------------
                */

                if (! $conversation->is_profile_completed) {

                    $this->log('ONBOARDING FLOW', [
                        'step' => $conversation->profile_step,
                    ], $requestId);

                    $response = $this->handleOnboarding($conversation, $text);

                    $this->storeOutgoing(
                        conversationId: $conversation->id,
                        response: $response,
                        requestId: $requestId
                    );

                    return $response;
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

                if (! $aiResponse || ! is_array($aiResponse)) {

                    $this->log('AI EMPTY RESPONSE', [], $requestId);

                    return [
                        'text' => 'Sorry, I’m having trouble right now. Please try again shortly.',
                        'attachments' => [],
                        'confidence' => 0,
                        'source' => 'error',
                    ];
                }

                $this->log('AI RESPONSE RECEIVED', [
                    'preview' => substr($aiResponse['text'] ?? '', 0, 150),
                    'source' => $aiResponse['source'] ?? null,
                ], $requestId);

                $this->storeOutgoing(
                    conversationId: $conversation->id,
                    response: $aiResponse,
                    requestId: $requestId
                );

                $conversation->update([
                    'last_activity_at' => now(),
                    'last_message_at' => now(),
                ]);

                return $aiResponse;
            });

        } catch (\Throwable $e) {

            Log::error('ChatbotProcessor FATAL', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ]);

            return [
                'text' => 'Technical issue occurred. Please try again.',
                'attachments' => [],
                'confidence' => 0,
                'source' => 'error',
            ];
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

        // STEP 0 → Ask Name
        if (! $conversation->profile_step) {

            $conversation->update([
                'profile_step' => 'ask_name',
            ]);

            return $this->systemReply(
                "Welcome 👋\nBefore we continue, please provide your *full name*."
            );
        }

        // STEP 1 → Save Name
        if ($conversation->profile_step === 'ask_name') {

            if (strlen($message) < 3) {
                return $this->systemReply('Please enter your full name.');
            }

            $conversation->update([
                'customer_name' => $message,
                'profile_step' => 'ask_email',
            ]);

            return $this->systemReply(
                "Thank you {$message} 😊\nNow please provide your *email address*."
            );
        }

        // STEP 2 → Save Email
        if ($conversation->profile_step === 'ask_email') {

            if (! filter_var($message, FILTER_VALIDATE_EMAIL)) {
                return $this->systemReply('❌ Please provide a valid email address.');
            }

            $conversation->update([
                'customer_email' => strtolower($message),
                'is_profile_completed' => 1,
                'profile_step' => 'completed',
            ]);

            return $this->systemReply(
                '✅ Thank you! You can now ask your questions.'
            );
        }

        return $this->systemReply('Please continue.');
    }

    protected function systemReply(string $text): array
    {
        return [
            'text' => $text,
            'attachments' => [],
            'confidence' => 1,
            'source' => 'system_onboarding',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | STORE OUTGOING RESPONSE
    |--------------------------------------------------------------------------
    */

    protected function storeOutgoing(int $conversationId, array $response, string $requestId): void
    {
        if (! empty($response['text'])) {

            $message = Message::create([
                'conversation_id' => $conversationId,
                'direction' => 'outgoing',
                'content' => $response['text'],
                'status' => 'pending',
                'source' => $response['source'] ?? null,
                'confidence' => $response['confidence'] ?? null,
                'meta' => [
                    'confidence' => $response['confidence'] ?? null,
                    'source' => $response['source'] ?? null,
                ],
            ]);

            $this->log('OUTGOING TEXT STORED', [
                'message_id' => $message->id,
            ], $requestId);
        }

        foreach ($response['attachments'] ?? [] as $attachment) {

            $att = Message::create([
                'conversation_id' => $conversationId,
                'direction' => 'outgoing',
                'content' => '[Attachment]',
                'type' => 'media',
                'status' => 'pending',
                'meta' => $attachment,
            ]);

            $this->log('OUTGOING ATTACHMENT STORED', [
                'message_id' => $att->id,
                'type' => $attachment['type'] ?? null,
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
