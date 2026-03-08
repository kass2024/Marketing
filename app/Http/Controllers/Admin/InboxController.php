<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;

class InboxController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->get('filter', 'all');
        $search = $request->get('search');
        $conversationId = $request->get('conversation');

        $query = Conversation::query();

        /*
        |--------------------------------------------------------------------------
        | FILTERS
        |--------------------------------------------------------------------------
        */

        if ($filter === 'unread') {
            $query->whereHas('messages', function ($q) {
                $q->where('direction', 'incoming')
                  ->where('is_read', 0);
            });
        }

        if ($filter === 'human') {
            $query->where('status', 'human');
        }

        if ($filter === 'bot') {
            $query->where('status', 'bot');
        }

        if ($filter === 'closed') {
            $query->where('status', 'closed');
        }

        /*
        |--------------------------------------------------------------------------
        | SEARCH
        |--------------------------------------------------------------------------
        */

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_email', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        /*
        |--------------------------------------------------------------------------
        | CONVERSATIONS LIST
        |--------------------------------------------------------------------------
        */

        $conversations = $query
            ->withCount([
                'messages as unread_count' => function ($q) {
                    $q->where('direction', 'incoming')
                      ->where('is_read', 0);
                }
            ])
            ->orderByDesc('last_activity_at')
            ->paginate(20);

        /*
        |--------------------------------------------------------------------------
        | ACTIVE CONVERSATION
        |--------------------------------------------------------------------------
        */

        $activeConversation = null;

        if ($conversationId) {

            $activeConversation = Conversation::with([
                'messages' => function ($q) {
                    $q->orderBy('created_at', 'asc'); // IMPORTANT FOR WHATSAPP FLOW
                }
            ])->find($conversationId);

            /*
            |--------------------------------------------------------------------------
            | MARK MESSAGES AS READ
            |--------------------------------------------------------------------------
            */

            if ($activeConversation) {

                Message::where('conversation_id', $activeConversation->id)
                    ->where('direction', 'incoming')
                    ->where('is_read', 0)
                    ->update([
                        'is_read' => 1,
                        'read_at' => now()
                    ]);
            }
        }

        return view('admin.inbox.index', [
            'conversations' => $conversations,
            'activeConversation' => $activeConversation,
            'filter' => $filter,
            'search' => $search
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SEND REPLY
    |--------------------------------------------------------------------------
    */

    public function reply(Request $request, Conversation $conversation)
    {
        $request->validate([
            'message' => 'required|string|max:5000'
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outgoing',
            'content' => $request->message,
            'status' => 'sent',
            'is_read' => 1,
            'created_at' => now()
        ]);

        $conversation->update([
            'status' => 'human',
            'last_activity_at' => now()
        ]);

        return redirect()->back();
    }

    /*
    |--------------------------------------------------------------------------
    | SWITCH BOT / HUMAN
    |--------------------------------------------------------------------------
    */

    public function toggle(Conversation $conversation)
    {
        if ($conversation->status === 'bot') {
            $conversation->update(['status' => 'human']);
        } else {
            $conversation->update(['status' => 'bot']);
        }

        return redirect()->back();
    }

    /*
    |--------------------------------------------------------------------------
    | CLOSE CONVERSATION
    |--------------------------------------------------------------------------
    */

    public function close(Conversation $conversation)
    {
        $conversation->update([
            'status' => 'closed'
        ]);

        return redirect()->back();
    }
}