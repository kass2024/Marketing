namespace App\Services\Chatbot;

use App\Models\ChatbotNode;
use App\Models\ConversationState;
use App\Models\Message;

class FlowEngine
{
    public function start($conversation, $chatbot)
    {
        $firstNode = ChatbotNode::where('chatbot_id', $chatbot->id)
            ->whereNull('parent_node_id')
            ->first();

        return $this->executeNode($conversation, $firstNode);
    }

    public function continue($conversation, $userInput)
    {
        $state = ConversationState::where('conversation_id', $conversation->id)
            ->latest()
            ->first();

        $currentNode = ChatbotNode::find($state->node_id);

        $nextNode = ChatbotNode::where('parent_node_id', $currentNode->id)
            ->first();

        return $this->executeNode($conversation, $nextNode);
    }

    protected function executeNode($conversation, $node)
    {
        if (!$node) {
            $conversation->update(['status' => 'completed']);
            return;
        }

        Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outgoing',
            'content' => $node->message,
        ]);

        ConversationState::create([
            'conversation_id' => $conversation->id,
            'node_id' => $node->id,
        ]);

        app(MessageDispatcher::class)
            ->send($conversation->meta_user_id, $node->message);
    }
}