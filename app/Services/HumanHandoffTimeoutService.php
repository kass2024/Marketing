<?php

namespace App\Services;

use App\Models\Conversation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HumanHandoffTimeoutService
{
    public function checkAndRelease(Conversation $conversation): bool
    {
        if (! in_array($conversation->status, [Conversation::STATUS_HUMAN, Conversation::STATUS_ESCALATED], true)) {
            return false;
        }

        if (! config('chat.enable_bot_fallback', true)) {
            return false;
        }

        $noAgentTimeout = (int) config('chat.no_agent_session_timeout', 300);
        $silenceTimeout = (int) (config('chat.agent_silence_timeout') ?? config('chat.bot_fallback_timeout', 900));

        if (! $conversation->assigned_agent_id) {
            $t0 = $conversation->escalation_started_at ?? $conversation->updated_at;
            if ($t0 && now()->diffInSeconds($t0) >= $noAgentTimeout) {
                $this->releaseToBot($conversation, 'no_agent_session');

                return true;
            }

            return false;
        }

        $lastIncoming = $conversation->messages()
            ->where('direction', 'incoming')
            ->latest('id')
            ->first();

        if (! $lastIncoming || ! $lastIncoming->created_at) {
            return false;
        }

        $lastAgentReply = $conversation->messages()
            ->where('direction', 'outgoing')
            ->where('source', 'agent')
            ->latest('id')
            ->first();

        if ($lastAgentReply && $lastIncoming->created_at->lte($lastAgentReply->created_at)) {
            return false;
        }

        if (now()->diffInSeconds($lastIncoming->created_at) >= $silenceTimeout) {
            $this->releaseToBot($conversation, 'agent_silent');

            return true;
        }

        return false;
    }

    protected function releaseToBot(Conversation $conversation, string $reason): void
    {
        $conversation->update([
            'status' => Conversation::STATUS_BOT,
            'assigned_agent_id' => null,
            'escalation_started_at' => null,
        ]);

        Log::info('Conversation returned to bot (timeout)', [
            'conversation_id' => $conversation->id,
            'reason' => $reason,
        ]);

        $this->notifyCustomer($conversation);
    }

    protected function notifyCustomer(Conversation $conversation): void
    {
        $phone = $conversation->phone_number;
        if (! $phone) {
            return;
        }

        $phoneNumberId = config('services.whatsapp.phone_number_id');
        $token = config('services.whatsapp.access_token');
        if (! $phoneNumberId || ! $token) {
            return;
        }

        $endpoint = config('services.whatsapp.graph_url').'/'
            .config('services.whatsapp.graph_version').'/'
            .$phoneNumberId.'/messages';

        try {
            Http::withToken($token)
                ->timeout((int) config('services.api.timeout', 30))
                ->post($endpoint, [
                    'messaging_product' => 'whatsapp',
                    'to' => $phone,
                    'type' => 'text',
                    'text' => [
                        'body' => '🤖 No agent replied in time. I\'m here again — how can I help?',
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::warning('Fallback-to-bot WhatsApp notify failed', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
