<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\Conversation;
use App\Models\User;
use App\Services\AgentNotifier;

class MonitorAgents extends Command
{
    protected $signature = 'agents:monitor';
    protected $description = 'Escalate conversation between agents before returning to AI';

    public function handle()
    {
        $timeout = 2; // minutes

        $limit = Carbon::now()->subMinutes($timeout);

        $conversations = Conversation::where('status','human')
            ->whereNotNull('escalation_started_at')
            ->where('escalation_started_at','<',$limit)
            ->get();

        foreach ($conversations as $conversation) {

            $level = $conversation->escalation_level;

            /*
            |--------------------------------------------------------------------------
            | NEXT AGENT
            |--------------------------------------------------------------------------
            */

            $agent = User::where('role','agent')
                ->where('status','active')
                ->orderBy('id')
                ->skip($level) // next agent
                ->first();

            if ($agent) {

                app(AgentNotifier::class)
                    ->notifyAgent($agent,$conversation);

                $conversation->update([
                    'escalation_level' => $level + 1,
                    'escalation_started_at' => now()
                ]);

                Log::info('ESCALATION_NEXT_AGENT',[
                    'conversation' => $conversation->id,
                    'agent' => $agent->id,
                    'level' => $level + 1
                ]);

            } else {

                /*
                |--------------------------------------------------------------------------
                | NO AGENT LEFT → RETURN TO BOT
                |--------------------------------------------------------------------------
                */

                $conversation->update([
                    'status' => 'bot'
                ]);

                Log::info('ESCALATION_RETURN_TO_AI',[
                    'conversation' => $conversation->id
                ]);

            }
        }
    }
}