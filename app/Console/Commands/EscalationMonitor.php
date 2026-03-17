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
    protected $description = 'Monitor escalated conversations and handle reassignment + fallback to bot';

    public function handle()
    {
        $agentTimeout = config('chat.agent_timeout', 60);
        $botFallbackTimeout = config('chat.bot_fallback_timeout', 300);

        Log::info('=== ESCALATION MONITOR STARTED ===');

        $conversations = Conversation::where('status', 'human')
            ->whereNotNull('assigned_agent_id')
            ->get();

        Log::info('Conversations fetched', [
            'count' => $conversations->count()
        ]);

        foreach ($conversations as $conversation) {

            $secondsSinceEscalation = $conversation->escalation_started_at
                ? now()->diffInSeconds($conversation->escalation_started_at)
                : 0;

            $secondsSinceLastMessage = $conversation->last_message_at
                ? now()->diffInSeconds($conversation->last_message_at)
                : $secondsSinceEscalation;

            Log::info('Checking conversation', [
                'conversation_id' => $conversation->id,
                'status' => $conversation->status,
                'assigned_agent_id' => $conversation->assigned_agent_id,
                'last_message_at' => $conversation->last_message_at,
                'escalation_started_at' => $conversation->escalation_started_at,
                'seconds_since_escalation' => $secondsSinceEscalation,
                'seconds_since_last_message' => $secondsSinceLastMessage
            ]);

            /*
            |--------------------------------------------------------------------------
            | 1️⃣ AGENT REASSIGNMENT
            |--------------------------------------------------------------------------
            */
            if ($secondsSinceEscalation > $agentTimeout) {

                Log::warning('Agent timeout reached → reassigning', [
                    'conversation_id' => $conversation->id
                ]);

                $router = app(AgentRouter::class);
                $newAgent = $router->assign($conversation);

                if ($newAgent) {

                    Log::info('New agent assigned', [
                        'conversation_id' => $conversation->id,
                        'agent_id' => $newAgent->id
                    ]);

                    app(AgentNotifier::class)
                        ->notify($conversation, $newAgent);

                    $conversation->update([
                        'assigned_agent_id' => $newAgent->id,
                        'escalation_started_at' => now()
                    ]);
                } else {
                    Log::warning('No agent available for reassignment', [
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

                Log::warning('Bot fallback triggered', [
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

                        Log::info('Fallback message sent to user', [
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
        }

        Log::info('=== ESCALATION MONITOR FINISHED ===');

        $this->info('Escalation monitor executed successfully.');
    }
}