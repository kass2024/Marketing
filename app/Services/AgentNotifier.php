<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Conversation;
use App\Models\User;

class AgentNotifier
{
    /**
     * Send WhatsApp template notification to agent
     */
    public function notifyAgent(User $agent, Conversation $conversation): bool
    {
        try {

            /*
            |--------------------------------------------------------------------------
            | Validate configuration
            |--------------------------------------------------------------------------
            */

            $phoneNumberId = config('services.whatsapp.phone_number_id');
            $accessToken   = config('services.whatsapp.access_token');

            if (!$phoneNumberId || !$accessToken) {

                Log::error('AGENT_NOTIFICATION_CONFIG_MISSING', [
                    'phone_number_id' => $phoneNumberId,
                    'token_present' => !empty($accessToken)
                ]);

                return false;
            }

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
            | Normalize numbers
            |--------------------------------------------------------------------------
            */

            $agentPhone    = preg_replace('/[^0-9]/', '', $agent->whatsapp_number);
            $customerPhone = preg_replace('/[^0-9]/', '', $conversation->phone_number);

            /*
            |--------------------------------------------------------------------------
            | Dashboard link
            |--------------------------------------------------------------------------
            */

            $dashboardLink = config('app.url') . "/admin/inbox/" . $conversation->id;

            Log::info('AGENT_NOTIFICATION_START', [
                'agent_id' => $agent->id,
                'agent_name' => $agent->name,
                'agent_phone' => $agentPhone,
                'customer_phone' => $customerPhone,
                'conversation_id' => $conversation->id
            ]);

            /*
            |--------------------------------------------------------------------------
            | Send WhatsApp template
            |--------------------------------------------------------------------------
            */

            $response = Http::withToken($accessToken)
                ->timeout(20)
                ->retry(2, 500)
                ->post(
                    "https://graph.facebook.com/v19.0/{$phoneNumberId}/messages",
                    [
                        "messaging_product" => "whatsapp",
                        "to" => $agentPhone,
                        "type" => "template",
                        "template" => [
                            "name" => "agent_support_alert",
                            "language" => [
                                "code" => "en"
                            ],
                            "components" => [
                                [
                                    "type" => "body",
                                    "parameters" => [
                                        [
                                            "type" => "text",
                                            "text" => $customerPhone
                                        ],
                                        [
                                            "type" => "text",
                                            "text" => $dashboardLink
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                );

            /*
            |--------------------------------------------------------------------------
            | Handle Meta response
            |--------------------------------------------------------------------------
            */

            if ($response->failed()) {

                Log::error('AGENT_NOTIFICATION_META_ERROR', [
                    'agent_id' => $agent->id,
                    'agent_phone' => $agentPhone,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);

                return false;
            }

            Log::info('AGENT_NOTIFICATION_SENT', [
                'agent_id' => $agent->id,
                'agent_name' => $agent->name,
                'agent_phone' => $agentPhone,
                'conversation_id' => $conversation->id,
                'meta_response' => $response->json()
            ]);

            return true;

        } catch (\Throwable $e) {

            Log::error('AGENT_NOTIFICATION_EXCEPTION', [
                'agent_id' => $agent->id ?? null,
                'conversation_id' => $conversation->id ?? null,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }
}