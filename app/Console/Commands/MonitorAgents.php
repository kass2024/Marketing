<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Models\Conversation;
use Carbon\Carbon;

class MonitorAgents extends Command
{
    protected $signature = 'agents:monitor';
    protected $description = 'Return conversations to AI if agents do not respond';

    public function handle()
    {

        $timeout = config('chat.agent_timeout', 5);

        $limit = Carbon::now()->subMinutes($timeout);

        $conversations = Conversation::where('status','human')
            ->where('last_activity_at','<',$limit)
            ->get();

        foreach ($conversations as $conversation) {

            $conversation->update([
                'status' => 'bot'
            ]);

            Log::info('AUTO_RETURN_TO_BOT',[
                'conversation_id' => $conversation->id
            ]);
        }

        return 0;
    }
}