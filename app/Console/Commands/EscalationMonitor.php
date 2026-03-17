<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Conversation;
use App\Services\AgentRouter;
use App\Services\AgentNotifier;
use App\Services\Chatbot\MessageDispatcher;
use Illuminate\Support\Facades\Log;

class EscalationMonitor extends Command
{
    protected $signature = 'agents:monitor';
    protected $description = 'Monitor escalations, assign agents, reassign if needed, and fallback to bot';

    public function handle()
    {
        $agentTimeout = config('chat.agent_timeout', 60);
        $botFallbackTimeout = config('chat.bot_fallback_timeout', 300);

        Log::info('=== ESCALATION MONITOR STARTED ===');

        // 🔥 IMPORTANT: DO NOT FILTER BY assigned_agent_id
        $conversations = Conversation::where('status', 'human')->get();

        Log::info('Conversations fetched', [
            'count' => $conversations->count()
        ]);

        foreach ($conversations as $conversation) {

            try {

                /*
                |--------------------------------------------------------------------------
                | 0️⃣ AUTO ASSIGN AGENT IF MISSING
                |--------------------------------------------------------------------------
                */
                if (!$conversation->assigned_agent_id) {

                    Log::warning('No agent assigned → assigning...', [
                        'conversation_id' => $conversation->id
                    ]);

                    $agent = app(AgentRouter::class)->assign($conversation);

                    if ($agent) {

                        $conversation->update([
                            'assigned_agent_id' => $agent->id,
                            'escalation_started_at' => now()
                        ]);

                        Log::info('Agent assigned', [
                            'conversation_id' => $conversation->id,
                            'agent_id' => $agent->id
                        ]);

                    } else {

                        Log::warning('No agents available → skipping', [
                            'conversation_id' => $conversation->id
                        ]);

                        continue;
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | TIME CALCULATIONS
                |--------------------------------------------------------------------------
                */
                $secondsSinceEscalation = $conversation->escalation_started_at
                    ? now()->diffInSeconds($conversation->escalation_started_at)
                    : 0;

                $secondsSinceLastMessage = $conversation->last_message_at
                    ? now()->diffInSeconds($conversation->last_message_at)
                    : $secondsSinceEscalation;

                Log::info('Conversation check', [
                    'id' => $conversation->id,
                    'agent_id' => $conversation->assigned_agent_id,
                    'since_escalation' => $secondsSinceEscalation,
                    'since_last_message' => $secondsSinceLastMessage
                ]);

                /*
                |--------------------------------------------------------------------------
                | 1️⃣ AGENT REASSIGNMENT
                |--------------------------------------------------------------------------
                */
                if ($secondsSinceEscalation > $agentTimeout) {

                    Log::warning('Agent timeout → reassigning', [
                        'conversation_id' => $conversation->id
                    ]);

                    $newAgent = app(AgentRouter::class)->assign($conversation);

                    if ($newAgent) {

                        $conversation->update([
                            'assigned_agent_id' => $newAgent->id,
                            'escalation_started_at' => now()
                        ]);

                        app(AgentNotifier::class)
                            ->notify($conversation, $newAgent);

                        Log::info('Reassigned to new agent', [
                            'conversation_id' => $conversation->id,
                            'agent_id' => $newAgent->id
                        ]);

                    } else {

                        Log::warning('Reassignment failed (no agent)', [
                            'conversation_id' => $conversation->id
                        ]);
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | 2️⃣ AUTO FALLBACK TO BOT
                |--------------------------------------------------------------------------
                */
                if ($secondsSinceLastMessage > $botFallbackTimeout) {

                    Log::warning('Fallback to bot triggered', [
                        'conversation_id' => $conversation->id
                    ]);

                    $conversation->update([
                        'status' => 'bot',
                        'assigned_agent_id' => null,
                        'escalation_started_at' => null
                    ]);

                    try {

                        if ($conversation->platform && $conversation->user_identifier) {

                            app(MessageDispatcher::class)->send(
                                $conversation->platform,
                                $conversation->user_identifier,
                                [
                                    'text' => "🤖 No agent responded in time. I'm back to assist you!"
                                ]
                            );

                            Log::info('User notified of fallback', [
                                'conversation_id' => $conversation->id
                            ]);
                        }

                    } catch (\Throwable $e) {

                        Log::error('Fallback message failed', [
                            'conversation_id' => $conversation->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

            } catch (\Throwable $e) {

                Log::error('Escalation monitor error', [
                    'conversation_id' => $conversation->id ?? null,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('=== ESCALATION MONITOR FINISHED ===');

        $this->info('Escalation monitor executed successfully.');
    }
}