<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Conversation;
use App\Services\AgentRouter;
use App\Services\AgentNotifier;

class EscalationMonitor extends Command
{

protected $signature = 'agents:monitor';
protected $description = 'Check agent response timeout';

public function handle()
{

$conversations = Conversation::where('status','human')
->whereNotNull('assigned_agent_id')
->whereNull('last_message_at')
->get();

foreach($conversations as $conversation){

$timeout = now()->diffInSeconds($conversation->escalation_started_at);

if($timeout > 60){

$router = app(AgentRouter::class);
$newAgent = $router->assign($conversation);

if($newAgent){

app(AgentNotifier::class)
->notify($conversation,$newAgent);

$conversation->update([
'escalation_started_at'=>now()
]);

}

}

}

}
}