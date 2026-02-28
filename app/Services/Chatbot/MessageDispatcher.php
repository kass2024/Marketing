public function send(string $to, string $message): void
{
    \Log::info('Dispatcher started');

    $platform = \App\Models\PlatformMetaConnection::first();

    if (!$platform) {
        \Log::error('No platform found');
        return;
    }

    \Log::info('Platform found', [
        'phone_number_id' => $platform->whatsapp_phone_number_id,
    ]);

    if (!$platform->whatsapp_phone_number_id) {
        \Log::error('No WhatsApp phone number ID saved');
        return;
    }

    try {
        $token = decrypt($platform->access_token);
        \Log::info('Token decrypted successfully');
    } catch (\Throwable $e) {
        \Log::error('Token decrypt failed', ['error' => $e->getMessage()]);
        return;
    }

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