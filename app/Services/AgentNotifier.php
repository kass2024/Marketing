<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Conversation;
use App\Models\User;

class AgentNotifier
{
    /**
     * Notify agent about new conversation escalation
     */
    public function notifyAgent(User $agent, Conversation $conversation): bool
    {
        try {

            /*
            |--------------------------------------------------------------------------
            | Validate agent phone
            |--------------------------------------------------------------------------
            */

            if (empty($agent->whatsapp_number)) {

                Log::warning('AGENT_NOTIFICATION_NO_PHONE', [
                    'agent_id' => $agent->id
                ]);

                return false;
            }

            /*
            |--------------------------------------------------------------------------
            | Normalize phone number (remove spaces + symbols)
            |--------------------------------------------------------------------------
            */

            $agentPhone = preg_replace('/[^0-9]/', '', $agent->whatsapp_number);
            $customerPhone = preg_replace('/[^0-9]/', '', $conversation->phone_number);

            /*
            |--------------------------------------------------------------------------
            | Build notification message
            |--------------------------------------------------------------------------
            */

            $message =
                "⚠️ New support request\n\n".
                "Customer: {$customerPhone}\n\n".
                "Open chat:\n".
                "https://wa.me/{$customerPhone}";

            /*
            |--------------------------------------------------------------------------
            | Send WhatsApp message via Meta API
            |--------------------------------------------------------------------------
            */

            $response = Http::withToken(config('services.whatsapp.token'))
                ->timeout(15)
                ->retry(2, 500)
                ->post(
                    "https://graph.facebook.com/v19.0/".config('services.whatsapp.phone_number_id')."/messages",
                    [
                        "messaging_product" => "whatsapp",
                        "to" => $agentPhone,
                        "type" => "text",
                        "text" => [
                            "body" => $message
                        ]
                    ]
                );

            /*
            |--------------------------------------------------------------------------
            | Check Meta API response
            |--------------------------------------------------------------------------
            */

            if ($response->failed()) {

                Log::error('AGENT_NOTIFICATION_META_ERROR', [
                    'agent_id' => $agent->id,
                    'phone' => $agentPhone,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                return false;
            }

            /*
            |--------------------------------------------------------------------------
            | Success log
            |--------------------------------------------------------------------------
            */

            Log::info('AGENT_NOTIFICATION_SENT', [
                'agent_id' => $agent->id,
                'agent_name' => $agent->name,
                'phone' => $agentPhone,
                'conversation_id' => $conversation->id,
                'meta_response' => $response->json()
            ]);

            return true;

        } catch (\Throwable $e) {

            Log::error('AGENT_NOTIFICATION_EXCEPTION', [
                'agent_id' => $agent->id ?? null,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }
}