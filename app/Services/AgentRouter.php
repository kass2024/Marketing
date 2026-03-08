<?php

namespace App\Services;

use App\Models\User;
use App\Models\Conversation;
use Illuminate\Support\Facades\Log;

class AgentRouter
{

    /**
     * Assign an agent based on escalation level
     */
    public function assignAgent(Conversation $conversation): ?User
    {

        /*
        |--------------------------------------------------------------------------
        | Agent priority list
        |--------------------------------------------------------------------------
        */

        $priorityAgents = [4,5]; // add more IDs if needed

        /*
        |--------------------------------------------------------------------------
        | Determine escalation level
        |--------------------------------------------------------------------------
        */

        $level = $conversation->escalation_level ?? 1;

        $index = $level - 1;

        if (!isset($priorityAgents[$index])) {

            Log::warning('AGENT_ROUTER_NO_MORE_AGENTS', [
                'conversation_id' => $conversation->id,
                'level' => $level
            ]);

            return null;
        }

        $agentId = $priorityAgents[$index];

        $agent = User::where('id', $agentId)
            ->where('role','agent')
            ->where('status','active')
            ->first();

        if (!$agent) {

            Log::warning('AGENT_ROUTER_AGENT_NOT_AVAILABLE', [
                'agent_id' => $agentId
            ]);

            return null;
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
            'agent_name' => $agent->name,
            'level' => $level
        ]);

        return $agent;

    }

}