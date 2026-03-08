<x-app-layout>

<div class="h-screen bg-gray-100 flex overflow-hidden">

{{-- ================= SIDEBAR ================= --}}
<div id="sidebar"
class="w-[340px] bg-white border-r flex flex-col
md:flex relative z-20
fixed md:relative h-full md:h-auto
translate-x-0 md:translate-x-0
transition-transform duration-300">

{{-- HEADER --}}
<div class="p-4 border-b flex justify-between items-center">
<h2 class="font-semibold text-gray-700">Inbox</h2>

<a href="/admin/bulk"
class="text-xs bg-blue-600 text-white px-3 py-1 rounded-lg">
Bulk Send
</a>
</div>

{{-- SEARCH --}}
<div class="p-4 border-b">
<input
type="text"
name="search"
value="{{ $search }}"
placeholder="Search conversations..."
class="w-full bg-gray-100 rounded-lg px-4 py-2 border-0 focus:ring-2 focus:ring-blue-500">
</div>

{{-- FILTERS --}}
<div class="px-4 py-3 flex gap-2 text-xs flex-wrap">

@foreach(['all','unread','human','bot','closed'] as $f)

<a
href="?filter={{ $f }}"
class="px-3 py-1 rounded-full font-medium
{{ $filter === $f
? 'bg-blue-600 text-white'
: 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">

{{ ucfirst($f) }}

</a>

@endforeach

</div>


{{-- CONVERSATION LIST --}}
<div class="flex-1 overflow-y-auto">

@foreach($conversations as $conversation)

<a
href="?conversation={{ $conversation->id }}"
class="flex items-center gap-3 px-4 py-3 border-b hover:bg-gray-50
{{ request('conversation') == $conversation->id ? 'bg-gray-100' : '' }}">

<div class="relative">

<div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center font-semibold text-blue-600">

{{ strtoupper(substr($conversation->customer_name ?? 'U',0,1)) }}

</div>

{{-- ONLINE DOT --}}
@if($conversation->is_online ?? false)
<div class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 border-2 border-white rounded-full"></div>
@endif

</div>

<div class="flex-1">

<p class="text-sm font-semibold text-gray-800 flex items-center gap-2">
{{ $conversation->customer_name ?? $conversation->phone_number }}

@if($conversation->status === 'human')
<span class="text-[10px] bg-yellow-500 text-white px-2 py-0.5 rounded">
ESCALATED
</span>
@endif

</p>

<p class="text-xs text-gray-500 truncate">
{{ $conversation->customer_email }}
</p>

</div>

@if($conversation->unread_count > 0)
<span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full">
{{ $conversation->unread_count }}
</span>
@endif

</a>

@endforeach

</div>

<div class="p-3 border-t">
{{ $conversations->links() }}
</div>

</div>



{{-- ================= CHAT AREA ================= --}}
<div class="flex-1 flex flex-col h-full">

@if($activeConversation)

{{-- CHAT HEADER --}}
<div class="bg-white border-b px-4 py-3 flex justify-between items-center sticky top-0 z-10">

<div class="flex items-center gap-3">

{{-- MOBILE BACK --}}
<button onclick="toggleSidebar()" class="md:hidden text-gray-500">
☰
</button>

<div class="relative">

<div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center font-semibold text-blue-600">
{{ strtoupper(substr($activeConversation->customer_name ?? 'U',0,1)) }}
</div>

@if($activeConversation->is_online ?? false)
<div class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 border-2 border-white rounded-full"></div>
@endif

</div>

<div>

<p class="font-semibold text-gray-800">
{{ $activeConversation->customer_name }}
</p>

<p class="text-xs text-gray-500">
{{ $activeConversation->customer_email }}
</p>

</div>

</div>

<div class="flex gap-2">

<form method="POST" action="{{ route('admin.inbox.toggle',$activeConversation->id) }}">
@csrf

<button class="px-4 py-2 rounded-lg text-sm
{{ $activeConversation->status === 'bot'
? 'bg-blue-600 text-white'
: 'bg-yellow-500 text-white' }}">

{{ $activeConversation->status === 'bot'
? 'Switch to Human'
: 'Switch to Bot' }}

</button>

</form>

<form method="POST" action="{{ route('admin.inbox.close',$activeConversation->id) }}">
@csrf
<button class="px-4 py-2 bg-red-500 text-white rounded-lg text-sm">
Close
</button>
</form>

{{-- DELETE CONVERSATION --}}
<form method="POST"
action="{{ route('admin.inbox.delete',$activeConversation->id) }}"
onsubmit="return confirm('Are you sure you want to permanently delete this conversation?')">

@csrf
@method('DELETE')

<button class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm hover:bg-gray-900">
Delete
</button>

</form>

</div>

</div>



{{-- ================= MESSAGE STREAM ================= --}}
<div id="chatBox"
class="flex-1 overflow-y-auto p-6 space-y-4 bg-gray-100">

@foreach($activeConversation->messages as $message)

<div
data-message-id="{{ $message->id }}"
class="flex {{ $message->direction === 'outgoing' ? 'justify-end' : 'justify-start' }}">

<div class="max-w-[70%]">

<div class="px-4 py-2 text-sm rounded-lg shadow
{{ $message->direction === 'outgoing'
? 'bg-blue-600 text-white rounded-br-none'
: 'bg-white text-gray-800 rounded-bl-none' }}">

@if($message->media_type === 'image')
<img src="{{ $message->media_url }}" class="rounded-lg max-w-xs mb-2">
@endif

@if($message->media_type === 'document')
<a href="{{ $message->media_url }}" target="_blank"
class="underline text-blue-600 block mb-2">
📎 {{ $message->filename }}
</a>
@endif

@if($message->content)
{!! nl2br(e($message->content)) !!}
@endif

</div>

<div class="text-[11px] text-gray-400 mt-1 flex items-center gap-1
{{ $message->direction === 'outgoing' ? 'justify-end' : '' }}">

{{ $message->created_at->format('H:i') }}

{{-- MESSAGE STATUS --}}
@if($message->direction === 'outgoing')

@if($message->status === 'sent')
<span>✓</span>
@endif

@if($message->status === 'delivered')
<span>✓✓</span>
@endif

@if($message->status === 'read')
<span class="text-blue-500">✓✓</span>
@endif

@endif

</div>

</div>

</div>

@endforeach

</div>



{{-- ================= REPLY BOX ================= --}}
<div class="bg-white border-t p-3 sticky bottom-0">

<form
method="POST"
action="{{ route('admin.inbox.reply',$activeConversation->id) }}"
enctype="multipart/form-data">

@csrf

<div class="flex gap-2 items-center">

<input
type="text"
name="message"
placeholder="Type a message..."
class="flex-1 bg-gray-100 rounded-full px-4 py-3 border-0 focus:ring-2 focus:ring-blue-500">

<input type="file" name="attachment" class="text-sm">

<button
class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-full font-semibold">
Send
</button>

</div>

</form>

</div>

@else

<div class="flex-1 flex items-center justify-center text-gray-400 text-lg">
Select a conversation
</div>

@endif

</div>

</div>



{{-- ================= AUTO SCROLL ================= --}}
<script>

const chat = document.getElementById('chatBox');

if(chat){
chat.scrollTop = chat.scrollHeight;
}

</script>



{{-- ================= MOBILE SIDEBAR ================= --}}
<script>

function toggleSidebar(){

const sidebar = document.getElementById("sidebar");

sidebar.classList.toggle("-translate-x-full");

}

</script>



{{-- ================= LIVE POLLING ================= --}}
<script>

@if($activeConversation)

const conversationId = {{ $activeConversation->id }};
const chatBox = document.getElementById("chatBox");

let lastMessageId = document.querySelector('[data-message-id]:last-child')?.dataset.messageId || 0;

function fetchMessages(){

fetch(`/admin/inbox/${conversationId}/messages`)
.then(res => res.json())
.then(messages => {

messages.forEach(msg => {

if(msg.id > lastMessageId){

appendMessage(msg);
lastMessageId = msg.id;

}

});

});

}

function appendMessage(msg){

let content = '';

if(msg.media_type === 'image'){
content = `<img src="${msg.media_url}" class="max-w-xs rounded-lg mb-2">`;
}

if(msg.media_type === 'document'){
content = `<a href="${msg.media_url}" target="_blank">📎 ${msg.filename}</a>`;
}

if(msg.content){
content += `<p>${msg.content}</p>`;
}

const wrapper = document.createElement("div");

wrapper.className = "flex " + (msg.direction === "outgoing" ? "justify-end" : "justify-start");

wrapper.innerHTML = `
<div class="max-w-[70%]">
<div class="px-4 py-2 text-sm rounded-lg shadow
${msg.direction === 'outgoing'
? 'bg-blue-600 text-white rounded-br-none'
: 'bg-white text-gray-800 rounded-bl-none'}">
${content}
</div>
<div class="text-[11px] text-gray-400 mt-1">
${msg.time}
</div>
</div>
`;

chatBox.appendChild(wrapper);
chatBox.scrollTop = chatBox.scrollHeight;

}

setInterval(fetchMessages,3000);

@endif

</script>

</x-app-layout>