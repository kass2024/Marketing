class AgentNotifier
{
    public function notify($conversation)
    {

        $token = config('services.whatsapp.access_token');

        $endpoint =
        config('services.whatsapp.graph_url').'/'
        .config('services.whatsapp.graph_version').'/'
        .config('services.whatsapp.phone_number_id').'/messages';

        Http::withToken($token)->post($endpoint,[

        "messaging_product"=>"whatsapp",

        "to"=>config('support.agent_phone'),

        "type"=>"text",

        "text"=>[
        "body"=>"⚠️ Customer needs help: ".$conversation->phone_number
        ]

        ]);

    }
}