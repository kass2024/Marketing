<x-app-layout>

<div class="h-screen flex bg-gray-100 overflow-hidden">

{{-- ================= MOBILE OVERLAY ================= --}}
<div id="overlay"
class="fixed inset-0 bg-black/40 hidden z-30 md:hidden"
onclick="closeSidebar()"></div>


{{-- ================= SIDEBAR ================= --}}
<div id="sidebar"
class="fixed md:relative z-40
w-[320px] md:w-[340px]
bg-white border-r flex flex-col h-full
transform -translate-x-full md:translate-x-0
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

@if($conversation->is_online ?? false)
<div class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 border-2 border-white rounded-full"></div>
@endif

</div>

<div class="flex-1 min-w-0">

<p class="text-sm font-semibold text-gray-800 flex items-center gap-2 truncate">
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
<div class="flex-1 flex flex-col min-w-0 h-full">

@if($activeConversation)

{{-- HEADER --}}
<div class="bg-white border-b px-3 md:px-4 py-3 flex justify-between items-center">

<div class="flex items-center gap-3">

<button onclick="openSidebar()" class="md:hidden text-gray-600 text-lg">
☰
</button>

<div class="relative">

<div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center font-semibold text-blue-600">
{{ strtoupper(substr($activeConversation->customer_name ?? 'U',0,1)) }}
</div>

<div id="onlineIndicator"
class="absolute bottom-0 right-0 w-3 h-3 border-2 border-white rounded-full
{{ $activeConversation->is_online ? 'bg-green-500' : 'bg-gray-300' }}">
</div>

</div>

<div class="min-w-0">
<p class="font-semibold text-gray-800 truncate">
{{ $activeConversation->customer_name }}
</p>

<p class="text-xs text-gray-500 truncate">
{{ $activeConversation->customer_email }}
</p>
</div>

</div>


<div class="flex gap-2 flex-wrap">

<form method="POST" action="{{ route('admin.inbox.toggle',$activeConversation->id) }}">
@csrf
<button class="px-3 py-2 rounded-lg text-sm
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
<button class="px-3 py-2 bg-red-500 text-white rounded-lg text-sm">
Close
</button>
</form>

<form method="POST"
action="{{ route('admin.inbox.delete',$activeConversation->id) }}"
onsubmit="return confirm('Delete this conversation permanently?')">
@csrf
@method('DELETE')
<button class="px-3 py-2 bg-gray-800 text-white rounded-lg text-sm">
Delete
</button>
</form>

</div>

</div>



{{-- ================= MESSAGES ================= --}}
<div id="chatBox"
class="flex-1 overflow-y-auto px-3 md:px-6 py-4 space-y-3">

@foreach($activeConversation->messages as $message)

<div
data-message-id="{{ $message->id }}"
class="flex {{ $message->direction === 'outgoing' ? 'justify-end' : 'justify-start' }}">

<div class="max-w-[85%] md:max-w-[65%]">

<div class="px-3 py-2 text-sm rounded-lg shadow
{{ $message->direction === 'outgoing'
? 'bg-blue-600 text-white rounded-br-none'
: 'bg-white text-gray-800 rounded-bl-none' }}">

@if($message->content)
{!! nl2br(e($message->content)) !!}
@endif

</div>

<div
id="msg-status-{{ $message->id }}"
class="text-[11px] text-gray-400 mt-1 flex items-center gap-1
{{ $message->direction === 'outgoing' ? 'justify-end' : '' }}">

{{ $message->created_at->format('H:i') }}

@if($message->direction === 'outgoing')

@if($message->status === 'sent') ✓ @endif
@if($message->status === 'delivered') ✓✓ @endif
@if($message->status === 'read') <span class="text-blue-500">✓✓</span> @endif

@endif

</div>

</div>

</div>

@endforeach

</div>



{{-- ================= INPUT ================= --}}
<div class="bg-white border-t p-3">

<form
method="POST"
action="{{ route('admin.inbox.reply',$activeConversation->id) }}"
enctype="multipart/form-data">

@csrf

<div class="flex gap-2">

<input
type="text"
name="message"
placeholder="Type a message..."
class="flex-1 bg-gray-100 rounded-full px-4 py-3 border-0 focus:ring-2 focus:ring-blue-500">

<input type="file" name="attachment" class="text-xs md:text-sm">

<button
class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-full">
Send
</button>

</div>

</form>

</div>

@endif

</div>

</div>



{{-- ================= JS ================= --}}
<script>

function openSidebar(){
document.getElementById("sidebar").classList.remove("-translate-x-full");
document.getElementById("overlay").classList.remove("hidden");
}

function closeSidebar(){
document.getElementById("sidebar").classList.add("-translate-x-full");
document.getElementById("overlay").classList.add("hidden");
}

const chatBox = document.getElementById("chatBox");

function isAtBottom(){
if(!chatBox) return true;
return chatBox.scrollHeight - chatBox.scrollTop - chatBox.clientHeight < 100;
}

function scrollBottom(){
if(chatBox) chatBox.scrollTop = chatBox.scrollHeight;
}

if(chatBox) scrollBottom();

</script>



<script>

@if($activeConversation)

const conversationId = {{ $activeConversation->id }};
let lastMessageId = document.querySelector('[data-message-id]:last-child')?.dataset.messageId || 0;

function fetchMessages(){

const shouldScroll = isAtBottom();

fetch(`/admin/inbox/${conversationId}/messages`)
.then(res => res.json())
.then(data => {

updateOnline(data.online);

data.messages.forEach(msg => {

updateStatus(msg);

if(msg.id > lastMessageId){
appendMessage(msg);
lastMessageId = msg.id;
}

});

if(shouldScroll) scrollBottom();

});

}

function updateOnline(online){

const dot = document.getElementById("onlineIndicator");
if(!dot) return;

dot.classList.toggle("bg-green-500",online);
dot.classList.toggle("bg-gray-300",!online);

}

function updateStatus(msg){

if(!msg.status) return;

const el = document.getElementById("msg-status-"+msg.id);
if(!el) return;

let ticks = "";

if(msg.status==="sent") ticks="✓";
if(msg.status==="delivered") ticks="✓✓";
if(msg.status==="read") ticks='<span class="text-blue-500">✓✓</span>';

el.innerHTML = msg.time+" "+ticks;

}

function appendMessage(msg){

const wrapper = document.createElement("div");

wrapper.className =
"flex "+(msg.direction==="outgoing"?"justify-end":"justify-start");

wrapper.innerHTML = `
<div class="max-w-[85%] md:max-w-[65%]">
<div class="px-3 py-2 text-sm rounded-lg shadow
${msg.direction==="outgoing"
? 'bg-blue-600 text-white rounded-br-none'
: 'bg-white text-gray-800 rounded-bl-none'}">
${msg.content ?? ""}
</div>
<div id="msg-status-${msg.id}" class="text-[11px] text-gray-400 mt-1">${msg.time}</div>
</div>`;

chatBox.appendChild(wrapper);

}

setInterval(fetchMessages,3000);

@endif

</script>

</x-app-layout>