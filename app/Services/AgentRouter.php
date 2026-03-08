<?php

namespace App\Services;

use App\Models\User;
use App\Models\Conversation;
use Illuminate\Support\Facades\Log;

class AgentRouter
{

    /**
     * Assign an agent to a conversation
     */
    public function assignAgent(Conversation $conversation): ?User
    {

        /*
        |--------------------------------------------------------------------------
        | PRIORITY ORDER
        |--------------------------------------------------------------------------
        */

        $priorityAgents = [4, 5]; // first try ID 4 then ID 5

        foreach ($priorityAgents as $agentId) {

            $agent = User::where('id', $agentId)
                ->where('role', 'agent')
                ->where('status', 'active')
                ->first();

            if (!$agent) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Assign agent
            |--------------------------------------------------------------------------
            */

            $conversation->update([
                'agent_id' => $agent->id
            ]);

            Log::info('AGENT_ROUTER_ASSIGNED', [
                'conversation_id' => $conversation->id,
                'agent_id' => $agent->id,
                'agent_name' => $agent->name
            ]);

            return $agent;
        }

        /*
        |--------------------------------------------------------------------------
        | No agent available
        |--------------------------------------------------------------------------
        */

        Log::warning('AGENT_ROUTER_NO_AGENT_AVAILABLE', [
            'conversation_id' => $conversation->id
        ]);

        return null;
    }

}