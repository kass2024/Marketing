namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Http;

class MessageDispatcher
{
    public function send($recipientId, $message)
    {
        $token = decrypt(
            \App\Models\PlatformMetaConnection::first()->access_token
        );

        Http::post(
            config('services.meta.graph_url') . '/' .
            config('services.meta.graph_version') .
            '/me/messages',
            [
                'recipient' => ['id' => $recipientId],
                'message' => ['text' => $message],
                'access_token' => $token,
            ]
        );
    }
}