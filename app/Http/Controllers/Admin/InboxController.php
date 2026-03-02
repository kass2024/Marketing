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
            ->withCount(['messages as unread_count' => function ($q) {
                $q->where('direction','incoming')
                  ->where('is_read',0);
            }])
            ->orderByDesc('last_activity_at')
            ->paginate(20);

        $activeConversation = null;

        if ($request->conversation) {
            $activeConversation = Conversation::with('messages')
                ->find($request->conversation);

            if ($activeConversation) {
                $activeConversation->messages()
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

    public function reply(Request $request, Conversation $conversation)
    {
        $request->validate([
            'message' => 'required|string'
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outgoing',
            'content' => $request->message,
            'status' => 'sent'
        ]);

        $conversation->update([
            'status' => 'human',
            'last_activity_at' => now()
        ]);

        return back();
    }

    public function toggle(Conversation $conversation)
    {
        $conversation->status === 'bot'
            ? $conversation->markAsHuman()
            : $conversation->markAsBot();

        return back();
    }

    public function close(Conversation $conversation)
    {
        $conversation->close();
        return back();
    }
}