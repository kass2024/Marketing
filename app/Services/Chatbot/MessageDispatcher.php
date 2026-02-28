public function send(string $to, string $message): void
{
    $platform = \App\Models\PlatformMetaConnection::first();

    if (!$platform) {
        \Log::error('No platform connection found.');
        return;
    }

    $token = decrypt($platform->access_token);

    $response = \Illuminate\Support\Facades\Http::withToken($token)
        ->post(
            config('services.meta.graph_url') . '/' .
            config('services.meta.graph_version') . '/' .
            $platform->whatsapp_phone_number_id . '/messages',
            [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'text',
                'text' => [
                    'body' => $message
                ],
            ]
        );

    \Log::info('WhatsApp API Response', [
        'status' => $response->status(),
        'body' => $response->body(),
    ]);
}