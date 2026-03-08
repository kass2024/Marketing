<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Conversation;
use App\Models\User;

class AgentNotifier
{
    public function notifyAgent(User $agent, Conversation $conversation)
    {
        try {

            $customerPhone = $conversation->phone_number;

            $message =
            "⚠️ New support request\n\n".
            "Customer: ".$customerPhone."\n\n".
            "Open chat:\n".
            "https://wa.me/".$customerPhone;

            Http::withToken(config('services.whatsapp.token'))
                ->post(
                    "https://graph.facebook.com/v19.0/".config('services.whatsapp.phone_number_id')."/messages",
                    [
                        "messaging_product" => "whatsapp",
                        "to" => $agent->whatsapp_number,
                        "type" => "text",
                        "text" => [
                            "body" => $message
                        ]
                    ]
                );

            Log::info('AGENT_NOTIFICATION_SENT', [
                'agent' => $agent->name,
                'phone' => $agent->whatsapp_number,
                'conversation' => $conversation->id
            ]);

        } catch (\Throwable $e) {

            Log::error('AGENT_NOTIFICATION_FAILED', [
                'error' => $e->getMessage()
            ]);
        }
    }
}