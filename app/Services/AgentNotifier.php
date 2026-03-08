<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AgentNotifier
{

public function notify($conversation,$agent)
{

$token = config('services.whatsapp.access_token');

$endpoint =
config('services.whatsapp.graph_url').'/'
.config('services.whatsapp.graph_version').'/'
.config('services.whatsapp.phone_number_id').'/messages';

$waLink = "https://wa.me/".$conversation->phone_number;

$message = "🚨 New support request

Customer: ".$conversation->customer_name."

Phone: ".$conversation->phone_number."

Open chat:
".$waLink;

Http::withToken($token)->post($endpoint,[

"messaging_product"=>"whatsapp",

"to"=>$agent->whatsapp_number,

"type"=>"text",

"text"=>[
"body"=>$message
]

]);

}

}