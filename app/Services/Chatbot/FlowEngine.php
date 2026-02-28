<?php

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

        if (!$state) {
            return null;
        }

        $currentNode = ChatbotNode::find($state->node_id);

        if (!$currentNode) {
            return null;
        }

        $nextNode = ChatbotNode::where('parent_node_id', $currentNode->id)
            ->first();

        return $this->executeNode($conversation, $nextNode);
    }

    protected function executeNode($conversation, $node): ?string
    {
        if (!$node) {
            $conversation->update(['status' => 'completed']);
            return null;
        }

        // Save outgoing message
        Message::create([
            'conversation_id' => $conversation->id,
            'direction'       => 'outgoing',
            'content'         => $node->message,
        ]);

        // Save conversation state
        ConversationState::create([
            'conversation_id' => $conversation->id,
            'node_id'         => $node->id,
        ]);

        // IMPORTANT: return message instead of sending here
        return $node->message;
    }
}