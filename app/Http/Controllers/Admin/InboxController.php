<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\HumanHandoffTimeoutService;
use App\Services\WhatsAppAudioConverter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class InboxController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INBOX
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
            'conversation' => $conversationId,
        ]);

        $query = Conversation::query();

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

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%$search%")
                    ->orWhere('customer_email', 'like', "%$search%")
                    ->orWhere('phone_number', 'like', "%$search%");
            });
        }

        $conversations = $query
            ->with(['agent'])
            ->withCount([
                'messages as unread_count' => function ($q) {
                    $q->where('direction', 'incoming')
                        ->where('is_read', 0);
                },
            ])
            ->orderByDesc('last_activity_at')
            ->paginate(20);

        $activeConversation = null;

        if ($conversationId) {
            $activeConversation = Conversation::with([
                'messages' => function ($q) {
                    $q->orderBy('created_at', 'asc');
                },
                'agent',
            ])->find($conversationId);

            if ($activeConversation) {

                app(HumanHandoffTimeoutService::class)->checkAndRelease($activeConversation->fresh());

                $activeConversation = Conversation::with([
                    'messages' => function ($q) {
                        $q->orderBy('created_at', 'asc');
                    },
                    'agent',
                ])->find($activeConversation->id);

                Log::info('Conversation opened', [
                    'conversation_id' => $activeConversation->id,
                    'phone' => $activeConversation->phone_number,
                ]);

                Message::where('conversation_id', $activeConversation->id)
                    ->where('direction', 'incoming')
                    ->where('is_read', 0)
                    ->update([
                        'is_read' => 1,
                        'read_at' => now(),
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
    | ADMIN REPLY (TEXT + ATTACHMENTS)
    |--------------------------------------------------------------------------
    */

    public function reply(Request $request, Conversation $conversation)
    {
        $wantsJson = $request->ajax() && $request->acceptsJson();

        try {
            $request->validate([
                'message' => 'nullable|string|max:5000',
                'attachment' => [
                    'nullable',
                    'file',
                    'max:25600',
                    'mimetypes:image/jpeg,image/png,image/gif,image/webp,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,audio/mpeg,audio/mp3,audio/mp4,audio/x-m4a,audio/m4a,audio/ogg,audio/opus,audio/wav,audio/x-wav,audio/webm,video/webm,video/mp4,video/quicktime,video/3gpp',
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::channel('voice')->warning('Inbox reply validation failed', [
                'conversation_id' => $conversation->id,
                'errors' => $e->errors(),
                'attachment_mime' => $request->file('attachment')?->getMimeType(),
                'attachment_client_type' => $request->file('attachment')?->getClientMimeType(),
            ]);
            if ($wantsJson) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Validation failed',
                    'errors' => $e->errors(),
                ], 422);
            }
            throw $e;
        }

        if (! $request->hasFile('attachment') && ! filled(trim((string) $request->message))) {
            if ($wantsJson) {
                return response()->json(['ok' => false, 'message' => 'Enter a message or attach a file.'], 422);
            }

            return back()->withErrors(['message' => 'Enter a message or attach a file.']);
        }

        $text = $request->message;

        Log::info('Admin sending message', [
            'conversation' => $conversation->id,
            'phone' => $conversation->phone_number,
            'message' => $text,
        ]);
        Log::channel('voice')->info('Inbox reply accepted', [
            'conversation_id' => $conversation->id,
            'has_attachment' => $request->hasFile('attachment'),
            'attachment_mime' => $request->file('attachment')?->getMimeType(),
            'wants_json' => $wantsJson,
        ]);
        $conversation->update([
            'last_activity_at' => now(),
            'last_message_at' => now(),
        ]);
        /*
        |--------------------------------------------------------------------------
        | HANDLE FILE UPLOAD
        |--------------------------------------------------------------------------
        */

        $mediaType = null;
        $fileUrl = null;
        $filename = null;
        $path = null;

        if ($request->hasFile('attachment')) {

            $file = $request->file('attachment');

            $path = $file->store('whatsapp', 'public');

            $filename = $file->getClientOriginalName();

            $mime = (string) $file->getMimeType();

            /*
             * Browsers record mic voice as video/webm (Opus in WebM). Treat as audio so we ffmpeg→OGG/MP3,
             * WhatsApp gets type=audio, and the inbox UI uses <audio> instead of a broken <video> preview.
             */
            $isBrowserVoiceWebm = $mime === 'video/webm'
                || ($mime === 'application/octet-stream' && str_ends_with(strtolower($file->getClientOriginalName()), '.webm'));

            if (str_contains($mime, 'image')) {
                $mediaType = 'image';
            } elseif (str_starts_with($mime, 'audio/') || $isBrowserVoiceWebm) {
                $mediaType = 'audio';
                $absolute = Storage::disk('public')->path($path);
                $convertedAbs = app(WhatsAppAudioConverter::class)->toWhatsAppFormat($absolute);
                if ($convertedAbs && is_file($convertedAbs)) {
                    if ($convertedAbs !== $absolute && is_file($absolute)) {
                        @unlink($absolute);
                    }
                    $path = dirname($path).'/'.basename($convertedAbs);
                    $filename = basename($convertedAbs);
                }
                Log::channel('voice')->info('Admin inbox audio file prepared', [
                    'conversation_id' => $conversation->id,
                    'mime' => $mime,
                    'voice_webm' => $isBrowserVoiceWebm,
                    'stored_path' => $path,
                ]);
            } elseif (str_starts_with($mime, 'video/')) {
                $mediaType = 'video';
            } else {
                $mediaType = 'document';
            }

            $fileUrl = URL::to(Storage::disk('public')->url($path));

        }

        /*
        |--------------------------------------------------------------------------
        | STORE MESSAGE
        |--------------------------------------------------------------------------
        */

        $displayContent = $text;
        if ($mediaType === 'audio' && ($displayContent === null || $displayContent === '')) {
            $displayContent = '🎤 Voice note';
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outgoing',
            'content' => $displayContent ?? '',
            'type' => $mediaType ? 'media' : 'text',
            'media_type' => $mediaType,
            'media_url' => $fileUrl,
            'filename' => $filename,
            'status' => 'sending',
            'is_read' => 1,
            'source' => 'agent',
        ]);

        /*
        |--------------------------------------------------------------------------
        | WHATSAPP CONFIG
        |--------------------------------------------------------------------------
        */

        $phoneNumberId = config('services.whatsapp.phone_number_id');
        $token = config('services.whatsapp.access_token');

        $endpoint =
        config('services.whatsapp.graph_url').'/'
        .config('services.whatsapp.graph_version').'/'
        .$phoneNumberId.'/messages';

        Log::info('Sending WhatsApp request', [
            'endpoint' => $endpoint,
            'phone_number_id' => $phoneNumberId,
        ]);

        /*
        |--------------------------------------------------------------------------
        | SEND MESSAGE
        |--------------------------------------------------------------------------
        */

        try {

            $response = null;

            if (! $mediaType) {

                $response = Http::withToken($token)
                    ->timeout(config('services.api.timeout'))
                    ->post($endpoint, [
                        'messaging_product' => 'whatsapp',
                        'to' => $conversation->phone_number,
                        'type' => 'text',
                        'text' => [
                            'body' => $text,
                        ],
                    ]);

            } elseif ($mediaType === 'image') {

                $response = Http::withToken($token)
                    ->timeout(config('services.api.timeout'))
                    ->post($endpoint, [
                        'messaging_product' => 'whatsapp',
                        'to' => $conversation->phone_number,
                        'type' => 'image',
                        'image' => [
                            'link' => $fileUrl,
                        ],
                    ]);

            } elseif ($mediaType === 'video') {

                $response = Http::withToken($token)
                    ->timeout(config('services.api.timeout'))
                    ->post($endpoint, [
                        'messaging_product' => 'whatsapp',
                        'to' => $conversation->phone_number,
                        'type' => 'video',
                        'video' => [
                            'link' => $fileUrl,
                        ],
                    ]);

            } elseif ($mediaType === 'document') {

                $response = Http::withToken($token)
                    ->timeout(config('services.api.timeout'))
                    ->post($endpoint, [
                        'messaging_product' => 'whatsapp',
                        'to' => $conversation->phone_number,
                        'type' => 'document',
                        'document' => [
                            'link' => $fileUrl,
                            'filename' => $filename ?? 'file',
                        ],
                    ]);

            } elseif ($mediaType === 'audio') {

                $response = Http::withToken($token)
                    ->timeout(config('services.api.timeout'))
                    ->post($endpoint, [
                        'messaging_product' => 'whatsapp',
                        'to' => $conversation->phone_number,
                        'type' => 'audio',
                        'audio' => [
                            'link' => $fileUrl,
                        ],
                    ]);

            }

            if ($response === null) {
                throw new \RuntimeException('No WhatsApp payload built.');
            }

            Log::info('WhatsApp API response', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            if ($response->successful()) {

                $wamid = $response->json()['messages'][0]['id'] ?? null;

                $message->update([
                    'status' => 'sent',
                    'external_message_id' => $wamid,
                ]);
            } else {

                $message->update([
                    'status' => 'failed',
                ]);

                Log::error('WhatsApp send failed', [
                    'body' => $response->body(),
                ]);
                Log::channel('voice')->error('Admin inbox WhatsApp send failed', [
                    'conversation_id' => $conversation->id,
                    'message_id' => $message->id,
                    'media_type' => $mediaType,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

            }

        } catch (\Throwable $e) {

            $message->update([
                'status' => 'failed',
            ]);

            Log::error('WhatsApp exception', [
                'error' => $e->getMessage(),
            ]);
            Log::channel('voice')->error('Admin inbox WhatsApp exception', [
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);

        }

        /*
        |--------------------------------------------------------------------------
        | UPDATE CONVERSATION
        |--------------------------------------------------------------------------
        */

        $conversation->update([
            'status' => 'human',
            'last_activity_at' => now(),
            'escalation_started_at' => $conversation->escalation_started_at ?? now(),
        ]);

        $message = $message->fresh();

        if ($wantsJson) {
            return response()->json([
                'ok' => true,
                'message' => $this->messageToInboxArray($message),
            ]);
        }

        return back();

    }

    /*
    |--------------------------------------------------------------------------
    | BOT / HUMAN SWITCH
    |--------------------------------------------------------------------------
    */

    public function toggle(Conversation $conversation)
    {

        $newStatus = $conversation->status === 'bot'
        ? 'human'
        : 'bot';

        Log::info('Conversation mode switched', [
            'conversation' => $conversation->id,
            'status' => $newStatus,
        ]);

        if ($newStatus === 'bot') {
            $conversation->update([
                'status' => 'bot',
                'assigned_agent_id' => null,
                'escalation_started_at' => null,
            ]);
        } else {
            $conversation->update([
                'status' => 'human',
                'escalation_started_at' => now(),
            ]);
        }

        return back();

    }

    /*
    |--------------------------------------------------------------------------
    | LIVE CHAT FETCH
    |--------------------------------------------------------------------------
    */

    protected function messageToInboxArray(Message $m): array
    {
        $dm = $m->displayMedia();

        return [
            'id' => $m->id,
            'direction' => $m->direction,
            'content' => $m->content,
            'media_type' => $dm['type'],
            'media_url' => $dm['url'],
            'filename' => $dm['filename'],
            'source' => $m->source,
            'status' => $m->status ?? 'pending',
            'time' => optional($m->created_at)->format('H:i'),
        ];
    }

    public function fetchMessages(Conversation $conversation)
    {

        app(HumanHandoffTimeoutService::class)->checkAndRelease($conversation->fresh());
        $conversation = $conversation->fresh();

        $messages = $conversation->messages()
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn (Message $m) => $this->messageToInboxArray($m));

        /*
        |--------------------------------------------------------------------------
        | ONLINE DETECTION
        |--------------------------------------------------------------------------
        | online if last activity < 60 seconds
        */

        $lastIncoming = $conversation->messages()
            ->where('direction', 'incoming')
            ->latest('id')
            ->first();

        $online = $lastIncoming
            && $lastIncoming->created_at
            && $lastIncoming->created_at->gt(now()->subMinutes(3));

        $lastSeen = $lastIncoming && $lastIncoming->created_at
            ? $lastIncoming->created_at->diffForHumans()
            : null;

        return response()->json([

            'messages' => $messages,
            'online' => $online,
            'last_seen' => $lastSeen,
            'conversation_status' => $conversation->status,

        ]);

    }

    /*
    |--------------------------------------------------------------------------
    | CLOSE CONVERSATION
    |--------------------------------------------------------------------------
    */
    public function bulkSend(Request $request)
    {

        $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        $messageText = $request->message;

        $phoneNumberId = config('services.whatsapp.phone_number_id');
        $token = config('services.whatsapp.access_token');

        $endpoint =
        config('services.whatsapp.graph_url').'/'
        .config('services.whatsapp.graph_version').'/'
        .$phoneNumberId.'/messages';

        $conversations = Conversation::where('channel', 'whatsapp')
            ->where('is_active', 1)
            ->limit(50)
            ->get();

        foreach ($conversations as $conversation) {

            try {

                $response = Http::withToken($token)->post($endpoint, [

                    'messaging_product' => 'whatsapp',

                    'to' => $conversation->phone_number,

                    'type' => 'text',

                    'text' => [
                        'body' => str_replace(
                            '{name}',
                            $conversation->customer_name ?? '',
                            $messageText
                        ),
                    ],

                ]);

                if ($response->successful()) {

                    Message::create([
                        'conversation_id' => $conversation->id,
                        'direction' => 'outgoing',
                        'content' => $messageText,
                        'status' => 'sent',
                        'is_read' => 1,
                    ]);

                } else {

                    Log::error('Bulk send failed', [
                        'phone' => $conversation->phone_number,
                        'body' => $response->body(),
                    ]);

                }

                sleep(1);

            } catch (\Throwable $e) {

                Log::error('Bulk exception', [
                    'phone' => $conversation->phone_number,
                    'error' => $e->getMessage(),
                ]);

            }

        }

        return back()->with('success', 'Bulk messages sent');

    }

    public function close(Conversation $conversation)
    {

        Log::info('Conversation closed', [
            'conversation' => $conversation->id,
        ]);

        $conversation->update([
            'status' => 'closed',
        ]);

        return back();

    }

    public function deleteMessage(Conversation $conversation, Message $message)
    {
        if ((int) $message->conversation_id !== (int) $conversation->id) {
            abort(404);
        }

        Log::channel('voice')->info('Inbox message deleted', [
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'had_media' => filled($message->media_url),
        ]);

        $message->delete();

        if (request()->ajax() && request()->acceptsJson()) {
            return response()->json(['ok' => true]);
        }

        return back();
    }

    public function deleteConversation($id)
    {
        // Find the conversation
        $conversation = Conversation::findOrFail($id);

        // Delete all messages linked to this conversation
        $conversation->messages()->delete();

        // Delete the conversation itself
        $conversation->delete();

        // Redirect back to the inbox page
        return redirect('/admin/inbox')->with('success', 'Conversation deleted successfully.');
    }
}
