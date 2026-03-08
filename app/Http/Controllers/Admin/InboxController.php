<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InboxController extends Controller
{

    /*
    |--------------------------------------------------------------------------
    | INBOX LIST
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $filter = $request->get('filter', 'all');
        $search = $request->get('search');
        $conversationId = $request->get('conversation');

        Log::info('Inbox page opened', [
            'filter' => $filter,
            'search' => $search,
            'conversation_id' => $conversationId
        ]);

        $query = Conversation::query();

        if ($filter === 'unread') {
            $query->whereHas('messages', function ($q) {
                $q->where('direction','incoming')
                  ->where('is_read',0);
            });
        }

        if ($filter === 'human') $query->where('status','human');
        if ($filter === 'bot') $query->where('status','bot');
        if ($filter === 'closed') $query->where('status','closed');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('customer_name','like',"%$search%")
                  ->orWhere('customer_email','like',"%$search%")
                  ->orWhere('phone_number','like',"%$search%");
            });
        }

        $conversations = $query
            ->withCount([
                'messages as unread_count' => function ($q) {
                    $q->where('direction','incoming')
                      ->where('is_read',0);
                }
            ])
            ->orderByDesc('last_activity_at')
            ->paginate(20);


        $activeConversation = null;

        if ($conversationId) {

            $activeConversation = Conversation::with([
                'messages' => function ($q) {
                    $q->orderBy('created_at','asc');
                }
            ])->find($conversationId);

            if ($activeConversation) {

                Log::info('Conversation opened', [
                    'conversation_id' => $activeConversation->id,
                    'phone' => $activeConversation->phone_number
                ]);

                Message::where('conversation_id',$activeConversation->id)
                    ->where('direction','incoming')
                    ->where('is_read',0)
                    ->update([
                        'is_read' => 1,
                        'read_at' => now()
                    ]);
            }
        }

        return view('admin.inbox.index', compact(
            'conversations',
            'activeConversation',
            'filter',
            'search'
        ));
    }


    /*
    |--------------------------------------------------------------------------
    | SEND REPLY (META WHATSAPP CLOUD API)
    |--------------------------------------------------------------------------
    */

    public function reply(Request $request, Conversation $conversation)
    {
        $request->validate([
            'message' => 'required|string|max:5000'
        ]);

        Log::info('Admin sending reply', [
            'conversation_id' => $conversation->id,
            'phone' => $conversation->phone_number,
            'message' => $request->message
        ]);

        /*
        |--------------------------------------------------------------------------
        | SAVE MESSAGE LOCALLY
        |--------------------------------------------------------------------------
        */

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outgoing',
            'content' => $request->message,
            'status' => 'sending',
            'is_read' => 1
        ]);


        /*
        |--------------------------------------------------------------------------
        | SEND TO META WHATSAPP CLOUD API
        |--------------------------------------------------------------------------
        */

        try {

            $endpoint =
                config('services.whatsapp.graph_url') . '/' .
                config('services.whatsapp.graph_version') . '/' .
                $conversation->phone_number_id . '/messages';

            Log::info('Sending WhatsApp message', [
                'endpoint' => $endpoint
            ]);

            $response = Http::withToken(config('services.meta.token'))
                ->timeout(config('services.api.timeout'))
                ->post($endpoint, [

                    'messaging_product' => 'whatsapp',

                    'to' => $conversation->phone_number,

                    'type' => 'text',

                    'text' => [
                        'body' => $request->message
                    ]

                ]);

            Log::info('Meta API response', [
                'status' => $response->status(),
                'body' => $response->json()
            ]);


            if ($response->successful()) {

                $message->update([
                    'status' => 'sent'
                ]);

                Log::info('Message delivered successfully');

            } else {

                $message->update([
                    'status' => 'failed'
                ]);

                Log::error('WhatsApp message failed', [
                    'response' => $response->body()
                ]);
            }

        } catch (\Exception $e) {

            $message->update([
                'status' => 'failed'
            ]);

            Log::error('WhatsApp API exception', [
                'error' => $e->getMessage()
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | UPDATE CONVERSATION
        |--------------------------------------------------------------------------
        */

        $conversation->update([
            'status' => 'human',
            'last_activity_at' => now()
        ]);

        return back();
    }


    /*
    |--------------------------------------------------------------------------
    | SWITCH BOT / HUMAN
    |--------------------------------------------------------------------------
    */

    public function toggle(Conversation $conversation)
    {
        $newStatus = $conversation->status === 'bot' ? 'human' : 'bot';

        Log::info('Conversation mode changed', [
            'conversation_id' => $conversation->id,
            'new_status' => $newStatus
        ]);

        $conversation->update([
            'status' => $newStatus
        ]);

        return back();
    }


    /*
    |--------------------------------------------------------------------------
    | CLOSE CONVERSATION
    |--------------------------------------------------------------------------
    */

    public function close(Conversation $conversation)
    {
        Log::info('Conversation closed', [
            'conversation_id' => $conversation->id
        ]);

        $conversation->update([
            'status' => 'closed'
        ]);

        return back();
    }
}