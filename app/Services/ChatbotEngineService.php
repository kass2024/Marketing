<?php

namespace App\Services;

use App\Models\Chatbot;
use App\Models\Conversation;
use App\Models\ConversationState;
use App\Models\Message;
use Illuminate\Support\Facades\DB;

class ChatbotEngineService
{
    /**
     * Handle incoming WhatsApp message
     */
    public function handleIncoming(string $phone, string $text, int $clientId)
    {
        DB::beginTransaction();

        try {

            // 1️⃣ Find or create conversation
            $conversation = Conversation::firstOrCreate(
                [
                    'client_id' => $clientId,
                    'phone_number' => $phone,
                ],
                [
                    'status' => 'bot'
                ]
            );

            // 2️⃣ Save incoming message
            Message::create([
                'conversation_id' => $conversation->id,
                'direction' => 'incoming',
                'type' => 'text',
                'content' => $text,
            ]);

            // 3️⃣ If human takeover → stop bot
            if ($conversation->status === 'human') {
                DB::commit();
                return null;
            }

            // 4️⃣ If no state → start chatbot
            if (!$conversation->state) {
                return $this->startChatbot($conversation, $text);
            }

            // 5️⃣ Continue flow
            return $this->continueFlow($conversation, $text);

        } catch (\Exception $e) {

            DB::rollBack();
            throw $e;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Start Chatbot
    |--------------------------------------------------------------------------
    */
    protected function startChatbot(Conversation $conversation, string $text)
    {
        $chatbot = Chatbot::where('client_id', $conversation->client_id)
            ->where('status', 'active')
            ->first();

        if (!$chatbot) {
            DB::commit();
            return null;
        }

        // Match keyword trigger
        $trigger = $chatbot->triggers()
            ->where('trigger_type', 'keyword')
            ->where('keyword', 'like', strtolower($text))
            ->first();

        if (!$trigger) {
            // Try welcome trigger
            $trigger = $chatbot->triggers()
                ->where('trigger_type', 'welcome')
                ->first();
        }

        if (!$trigger) {
            DB::commit();
            return null;
        }

        $firstNode = $chatbot->nodes()->first();

        if (!$firstNode) {
            DB::commit();
            return null;
        }

        // Create conversation state
        ConversationState::create([
            'conversation_id' => $conversation->id,
            'current_node_id' => $firstNode->id,
            'last_interaction_at' => now(),
        ]);

        $this->sendNodeMessage($conversation, $firstNode);

        DB::commit();

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Continue Flow
    |--------------------------------------------------------------------------
    */
    protected function continueFlow(Conversation $conversation, string $text)
    {
        $state = $conversation->state;

        if (!$state || !$state->currentNode) {
            DB::commit();
            return null;
        }

        $currentNode = $state->currentNode;

        if (!$currentNode->nextNode) {
            DB::commit();
            return null;
        }

        $nextNode = $currentNode->nextNode;

        $state->update([
            'current_node_id' => $nextNode->id,
            'last_interaction_at' => now(),
        ]);

        $this->sendNodeMessage($conversation, $nextNode);

        DB::commit();

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Send Node Message
    |--------------------------------------------------------------------------
    */
    protected function sendNodeMessage(Conversation $conversation, $node)
    {
        Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outgoing',
            'type' => 'text',
            'content' => $node->content,
            'status' => 'sent'
        ]);

        // TODO:
        // Here you call your WhatsApp Cloud API send function
        // Example:
        // sendWhatsApp($conversation->phone_number, $node->content);
    }
}