namespace App\Services\Chatbot;

use App\Models\Chatbot;
use App\Models\Conversation;
use App\Models\Message;

class ChatbotProcessor
{
    public function process(array $payload)
    {
        $metaUserId = $payload['from'];
        $text = strtolower(trim($payload['text'] ?? ''));

        // 1️⃣ Find active conversation
        $conversation = Conversation::where('meta_user_id', $metaUserId)
            ->where('status', 'active')
            ->first();

        if (!$conversation) {
            return $this->startConversation($metaUserId, $text);
        }

        return app(FlowEngine::class)
            ->continue($conversation, $text);
    }

    protected function startConversation($metaUserId, $text)
    {
        // Only ONE active chatbot
        $chatbot = Chatbot::where('status', 'active')->first();

        if (!$chatbot) {
            return;
        }

        // Check triggers
        $triggerMatch = $chatbot->triggers()
            ->where('keyword', 'LIKE', "%$text%")
            ->exists();

        if (!$triggerMatch) {
            return;
        }

        $conversation = Conversation::create([
            'meta_user_id' => $metaUserId,
            'status' => 'active',
        ]);

        return app(FlowEngine::class)
            ->start($conversation, $chatbot);
    }
}