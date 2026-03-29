<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Services\AgentNotifier;
use App\Services\AgentRouter;
use App\Services\HumanHandoffTimeoutService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class EscalationMonitor extends Command
{
    protected $signature = 'agents:monitor';

    protected $description = 'Monitor escalations, assign agents, reassign if needed, and fallback to bot';

    public function handle()
    {
        $agentTimeout = (int) config('chat.agent_timeout', 60);

        Log::info('=== ESCALATION MONITOR STARTED ===');

        $conversations = Conversation::query()
            ->whereIn('status', [Conversation::STATUS_HUMAN, Conversation::STATUS_ESCALATED])
            ->get();

        Log::info('Conversations fetched', [
            'count' => $conversations->count(),
        ]);

        $handoff = app(HumanHandoffTimeoutService::class);

        foreach ($conversations as $conversation) {
            try {
                $conversation = $conversation->fresh();
                if (! $conversation) {
                    continue;
                }

                if (! $conversation->assigned_agent_id) {
                    Log::warning('No agent assigned → assigning...', [
                        'conversation_id' => $conversation->id,
                    ]);

                    $agent = app(AgentRouter::class)->assign($conversation);

                    if ($agent) {
                        $conversation->update([
                            'assigned_agent_id' => $agent->id,
                            'escalation_started_at' => now(),
                        ]);

                        Log::info('Agent assigned', [
                            'conversation_id' => $conversation->id,
                            'agent_id' => $agent->id,
                        ]);
                    } else {
                        Log::warning('No agents available', [
                            'conversation_id' => $conversation->id,
                        ]);
                    }

                    $conversation = $conversation->fresh();
                }

                if (config('chat.enable_agent_reassignment', true) && $conversation && $conversation->assigned_agent_id) {
                    $secondsSinceEscalation = $conversation->escalation_started_at
                        ? now()->diffInSeconds($conversation->escalation_started_at)
                        : 0;

                    Log::info('Conversation check', [
                        'id' => $conversation->id,
                        'agent_id' => $conversation->assigned_agent_id,
                        'since_escalation' => $secondsSinceEscalation,
                    ]);

                    if ($secondsSinceEscalation > $agentTimeout) {
                        Log::warning('Agent timeout → reassigning', [
                            'conversation_id' => $conversation->id,
                        ]);

                        $newAgent = app(AgentRouter::class)->assign($conversation);

                        if ($newAgent) {
                            $conversation->update([
                                'assigned_agent_id' => $newAgent->id,
                                'escalation_started_at' => now(),
                            ]);

                            app(AgentNotifier::class)->notify($conversation, $newAgent);

                            Log::info('Reassigned to new agent', [
                                'conversation_id' => $conversation->id,
                                'agent_id' => $newAgent->id,
                            ]);
                        } else {
                            Log::warning('Reassignment failed (no agent)', [
                                'conversation_id' => $conversation->id,
                            ]);
                        }

                        $conversation = $conversation->fresh();
                    }
                }

                if ($conversation) {
                    $handoff->checkAndRelease($conversation->fresh());
                }
            } catch (\Throwable $e) {
                Log::error('Escalation monitor error', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('=== ESCALATION MONITOR FINISHED ===');

        $this->info('Escalation monitor executed successfully.');
    }
}