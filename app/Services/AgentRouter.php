<?php

namespace App\Services;

use App\Models\User;
use App\Models\Conversation;
use Illuminate\Support\Facades\Log;

class AgentRouter
{

    /**
     * Assign best available agent
     */
    public function assignAgent(Conversation $conversation): ?User
    {

        try {

            /*
            |--------------------------------------------------------------------------
            | Get all active agents
            |--------------------------------------------------------------------------
            */

            $agents = User::where('role','agent')
                ->where('status','active')
                ->get();

            if ($agents->isEmpty()) {

                Log::warning('AGENT_ROUTER_NO_AGENTS');

                return null;
            }

            /*
            |--------------------------------------------------------------------------
            | Find agent with least active conversations
            |--------------------------------------------------------------------------
            */

            $selectedAgent = null;
            $minLoad = PHP_INT_MAX;

            foreach ($agents as $agent) {

                $activeLoad = Conversation::where('assigned_agent_id',$agent->id)
                    ->where('status','human')
                    ->count();

                if ($activeLoad < $minLoad) {

                    $minLoad = $activeLoad;
                    $selectedAgent = $agent;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Assign agent to conversation
            |--------------------------------------------------------------------------
            */

            if ($selectedAgent) {

                $conversation->update([
                    'assigned_agent_id' => $selectedAgent->id,
                    'status' => 'human'
                ]);

                Log::info('AGENT_ROUTER_ASSIGNED',[
                    'conversation_id' => $conversation->id,
                    'agent_id' => $selectedAgent->id,
                    'agent_name' => $selectedAgent->name,
                    'agent_load' => $minLoad
                ]);
            }

            return $selectedAgent;

        } catch (\Throwable $e) {

            Log::error('AGENT_ROUTER_ERROR',[
                'error'=>$e->getMessage()
            ]);

            return null;
        }
    }
}