<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Log;
use App\Models\ChatbotNode;
use App\Models\ConversationState;
use App\Models\Message;

class FlowEngine
{
    /*
    |--------------------------------------------------------------------------
    | Start Conversation
    |--------------------------------------------------------------------------
    */
    public function start($conversation, $chatbot): string
    {
        // First node = smallest ID of chatbot
        $firstNode = ChatbotNode::where('chatbot_id', $chatbot->id)
            ->orderBy('id')
            ->first();

        if (!$firstNode) {
            return $this->fallback($conversation);
        }

        return $this->executeNode($conversation, $firstNode);
    }

    /*
    |--------------------------------------------------------------------------
    | Continue Conversation
    |--------------------------------------------------------------------------
    */
    public function continue($conversation, string $userInput): string
    {
        $state = ConversationState::where('conversation_id', $conversation->id)
            ->latest()
            ->first();

        if (!$state) {
            return $this->fallback($conversation);
        }

        $currentNode = ChatbotNode::find($state->node_id);

        if (!$currentNode) {
            return $this->fallback($conversation);
        }

        /*
        |--------------------------------------------------------------------------
        | Handle Node Types
        |--------------------------------------------------------------------------
        */

        switch ($currentNode->type) {

            case 'question':
                return $this->handleQuestion($conversation, $currentNode, $userInput);

            case 'message':
            case 'delay':
            case 'condition':
            default:
                return $this->goToNextNode($conversation, $currentNode);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Execute Node
    |--------------------------------------------------------------------------
    */
    protected function executeNode($conversation, $node): string
    {
        if (!$node) {
            return $this->fallback($conversation);
        }

        // Save outgoing message
        Message::create([
            'conversation_id' => $conversation->id,
            'direction'       => 'outgoing',
            'content'         => $node->content ?? '',
        ]);

        // Save state
        ConversationState::updateOrCreate(
            ['conversation_id' => $conversation->id],
            ['node_id' => $node->id]
        );

        return $node->content ?: $this->fallback($conversation);
    }

    /*
    |--------------------------------------------------------------------------
    | Handle Question Node
    |--------------------------------------------------------------------------
    */
    protected function handleQuestion($conversation, $node, $userInput): string
    {
        $options = json_decode($node->options, true);

        if (is_array($options)) {
            foreach ($options as $option) {
                if (
                    isset($option['value'], $option['next_node_id']) &&
                    strtolower(trim($option['value'])) === strtolower(trim($userInput))
                ) {
                    $nextNode = ChatbotNode::find($option['next_node_id']);
                    return $this->executeNode($conversation, $nextNode);
                }
            }
        }

        // If no option matched, repeat question
        return $node->content;
    }

    /*
    |--------------------------------------------------------------------------
    | Go To Next Node
    |--------------------------------------------------------------------------
    */
    protected function goToNextNode($conversation, $currentNode): string
    {
        if (!$currentNode->next_node_id) {
            $conversation->update(['status' => 'closed']);
            return "Thank you for chatting with us 🙏";
        }

        $nextNode = ChatbotNode::find($currentNode->next_node_id);

        if (!$nextNode) {
            return $this->fallback($conversation);
        }

        return $this->executeNode($conversation, $nextNode);
    }

    /*
    |--------------------------------------------------------------------------
    | Fallback Reply (Durable)
    |--------------------------------------------------------------------------
    */
    protected function fallback($conversation): string
    {
        Log::info('Fallback triggered', [
            'conversation_id' => $conversation->id
        ]);

        return "Hello 👋 How can we help you today?";
    }
}