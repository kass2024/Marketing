<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\Conversation;
use App\Models\User;

class AgentNotifier
{

public function notify(Conversation $conversation, User $agent)
{

$customerPhone = $conversation->phone_number;

$message =
"⚠️ New support request\n\n".
"Customer: ".$customerPhone."\n\n".
"Open chat:\n".
"https://wa.me/".$customerPhone;

Http::post(
"https://graph.facebook.com/v19.0/".config('services.whatsapp.phone_number_id')."/messages",
[
"messaging_product"=>"whatsapp",
"to"=>$agent->whatsapp_number,
"type"=>"text",
"text"=>[
"body"=>$message
]
],
[
"headers"=>[
"Authorization"=>"Bearer ".config('services.whatsapp.token')
]
]
);

}

}