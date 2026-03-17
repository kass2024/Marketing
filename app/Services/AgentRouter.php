<?php

namespace App\Services;

use App\Models\User;
use App\Models\Conversation;
use Illuminate\Support\Facades\Log;

class AgentRouter
{
    /**
     * MAIN ENTRY POINT (used everywhere safely)
     */
    public function assign(Conversation $conversation): ?User
    {
        try {

            // 🔹 Load priority agents (configurable)
            $priorityAgents = config('chat.agent_priority', [4, 5]);

            /*
            |--------------------------------------------------------------------------
            | Determine escalation level safely
            |--------------------------------------------------------------------------
            */
            $level = (int) ($conversation->escalation_level ?? 1);

            if ($level < 1) {
                $level = 1;
            }

            $index = $level - 1;

            /*
            |--------------------------------------------------------------------------
            | Check if level exists
            |--------------------------------------------------------------------------
            */
            if (!isset($priorityAgents[$index])) {

                Log::warning('AGENT_ROUTER_NO_MORE_AGENTS', [
                    'conversation_id' => $conversation->id,
                    'level' => $level
                ]);

                return null;
            }

            $agentId = $priorityAgents[$index];

            /*
            |--------------------------------------------------------------------------
            | Fetch agent
            |--------------------------------------------------------------------------
            */
            $agent = $this->getAgentById($agentId);

            if (!$agent) {

                Log::warning('AGENT_ROUTER_AGENT_NOT_AVAILABLE', [
                    'conversation_id' => $conversation->id,
                    'agent_id' => $agentId
                ]);

                return null;
            }

            /*
            |--------------------------------------------------------------------------
            | Assign agent (only if changed)
            |--------------------------------------------------------------------------
            */
            if ($conversation->assigned_agent_id !== $agent->id) {

                $conversation->update([
                    'assigned_agent_id' => $agent->id,
                    'escalation_level' => $level,
                    'escalation_started_at' => now()
                ]);

                Log::info('AGENT_ROUTER_ASSIGNED', [
                    'conversation_id' => $conversation->id,
                    'agent_id' => $agent->id,
                    'agent_name' => $agent->name,
                    'level' => $level
                ]);
            }

            return $agent;

        } catch (\Throwable $e) {

            Log::error('AGENT_ROUTER_EXCEPTION', [
                'conversation_id' => $conversation->id ?? null,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * 🔥 BACKWARD COMPATIBILITY (CRITICAL FIX)
     * Fixes: undefined method assignAgent()
     */
    public function assignAgent(Conversation $conversation): ?User
    {
        return $this->assign($conversation);
    }

    /**
     * 🔥 EXTRA SAFETY (if other parts call getAgent)
     */
    public function getAgent(Conversation $conversation): ?User
    {
        return $this->assign($conversation);
    }

    /**
     * Escalate to next level
     */
    public function escalate(Conversation $conversation): ?User
    {
        try {

            $newLevel = (int) ($conversation->escalation_level ?? 1) + 1;

            $conversation->update([
                'escalation_level' => $newLevel
            ]);

            Log::info('AGENT_ROUTER_ESCALATING', [
                'conversation_id' => $conversation->id,
                'new_level' => $newLevel
            ]);

            return $this->assign($conversation);

        } catch (\Throwable $e) {

            Log::error('AGENT_ROUTER_ESCALATE_ERROR', [
                'conversation_id' => $conversation->id ?? null,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Reset escalation
     */
    public function reset(Conversation $conversation): void
    {
        try {

            $conversation->update([
                'escalation_level' => 1,
                'assigned_agent_id' => null,
                'escalation_started_at' => null
            ]);

            Log::info('AGENT_ROUTER_RESET', [
                'conversation_id' => $conversation->id
            ]);

        } catch (\Throwable $e) {

            Log::error('AGENT_ROUTER_RESET_ERROR', [
                'conversation_id' => $conversation->id ?? null,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 🔹 INTERNAL: Get agent safely
     */
    protected function getAgentById(int $agentId): ?User
    {
        return User::where('id', $agentId)
            ->where('role', 'agent')
            ->where('status', 'active')
            ->first();
    }
}