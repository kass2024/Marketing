<x-app-layout>

<div class="h-screen flex bg-[#f0f2f5] overflow-hidden">

{{-- SIDEBAR --}}
<div id="sidebar"
class="w-[320px] md:w-[360px] bg-white border-r flex flex-col
fixed md:relative inset-y-0 left-0 z-40
transform -translate-x-full md:translate-x-0
transition-transform duration-300">

{{-- HEADER --}}
<div class="px-4 py-3 bg-[#f0f2f5] border-b flex justify-between items-center">

<h2 class="font-semibold text-gray-700 text-sm">
Inbox
</h2>

<a href="/admin/bulk"
class="bg-green-500 text-white text-xs px-3 py-1 rounded-md">
Bulk Send
</a>

</div>


{{-- SEARCH --}}
<div class="p-3 border-b">

<input
type="text"
name="search"
value="{{ $search }}"
placeholder="Search conversations"
class="w-full bg-[#f0f2f5] rounded-lg px-4 py-2 text-sm border-0 focus:ring-2 focus:ring-green-500">

</div>


{{-- FILTERS --}}
<div class="flex gap-2 px-3 py-2 text-xs overflow-x-auto">

@foreach(['all','unread','human','bot','closed'] as $f)

<a
href="?filter={{ $f }}"
class="px-3 py-1 rounded-full whitespace-nowrap
{{ $filter === $f
? 'bg-green-500 text-white'
: 'bg-gray-200 text-gray-700' }}">

{{ ucfirst($f) }}

</a>

@endforeach

</div>


{{-- CHAT LIST --}}
<div class="flex-1 overflow-y-auto">

@foreach($conversations as $conversation)

<a
href="?conversation={{ $conversation->id }}"
class="flex items-center gap-3 px-4 py-3 border-b hover:bg-gray-50
{{ request('conversation') == $conversation->id ? 'bg-gray-100' : '' }}">

<div class="relative">

<div class="w-11 h-11 rounded-full bg-green-500 text-white flex items-center justify-center font-semibold">

{{ strtoupper(substr($conversation->customer_name ?? 'U',0,1)) }}

</div>

@if($conversation->is_online)
<div class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 border-2 border-white rounded-full"></div>
@endif

</div>


<div class="flex-1 min-w-0">

<p class="text-sm font-semibold text-gray-800 truncate">

{{ $conversation->customer_name ?? $conversation->phone_number }}

@if($conversation->status === 'human')
<span class="ml-1 text-[10px] bg-yellow-500 text-white px-2 rounded">
ESCALATED
</span>
@endif

</p>

<p class="text-xs text-gray-500 truncate">
{{ $conversation->customer_email }}
</p>

</div>

@if($conversation->unread_count > 0)
<span class="bg-green-500 text-white text-xs px-2 py-[2px] rounded-full">
{{ $conversation->unread_count }}
</span>
@endif

</a>

@endforeach

</div>


<div class="p-2 border-t">
{{ $conversations->links() }}
</div>

</div>


{{-- CHAT AREA --}}
<div class="flex-1 flex flex-col h-full min-w-0">

@if($activeConversation)

{{-- HEADER --}}
<div class="bg-[#f0f2f5] border-b px-4 py-3 flex justify-between items-center">

<div class="flex items-center gap-3">

<button onclick="toggleSidebar()" class="md:hidden text-xl">
☰
</button>

<div class="relative">

<div class="w-10 h-10 rounded-full bg-green-500 text-white flex items-center justify-center font-semibold">

{{ strtoupper(substr($activeConversation->customer_name ?? 'U',0,1)) }}

</div>

<div
class="absolute bottom-0 right-0 w-3 h-3 border-2 border-white rounded-full
{{ $activeConversation->is_online ? 'bg-green-500' : 'bg-gray-300' }}">
</div>

</div>

<div class="min-w-0">

<p class="font-semibold text-gray-800 text-sm truncate">
{{ $activeConversation->customer_name }}
</p>

<p class="text-xs text-gray-500 truncate">
{{ $activeConversation->customer_email }}
</p>

</div>

</div>


{{-- DESKTOP ACTIONS --}}
<div class="hidden md:flex items-center gap-2">

<form method="POST" action="{{ route('admin.inbox.toggle',$activeConversation->id) }}">
@csrf
<button class="text-xs px-3 py-1 bg-green-600 text-white rounded">
Switch
</button>
</form>

<form method="POST" action="{{ route('admin.inbox.close',$activeConversation->id) }}">
@csrf
<button class="text-xs px-3 py-1 bg-red-500 text-white rounded">
Close
</button>
</form>

<form method="POST"
action="{{ route('admin.inbox.delete',$activeConversation->id) }}"
onsubmit="return confirm('Delete conversation?')">
@csrf
@method('DELETE')

<button class="text-xs px-3 py-1 bg-gray-800 text-white rounded">
Delete
</button>

</form>

</div>


{{-- MOBILE MENU --}}
<div class="relative md:hidden">

<button onclick="toggleMenu()" class="text-xl">
⋮
</button>

<div id="menu"
class="hidden absolute right-0 top-8 bg-white shadow-xl rounded-lg border w-40 z-50">

<form method="POST" action="{{ route('admin.inbox.toggle',$activeConversation->id) }}">
@csrf
<button class="block w-full text-left px-4 py-2 hover:bg-gray-100">
Switch
</button>
</form>

<form method="POST" action="{{ route('admin.inbox.close',$activeConversation->id) }}">
@csrf
<button class="block w-full text-left px-4 py-2 hover:bg-gray-100">
Close
</button>
</form>

<form method="POST"
action="{{ route('admin.inbox.delete',$activeConversation->id) }}"
onsubmit="return confirm('Delete conversation?')">
@csrf
@method('DELETE')

<button class="block w-full text-left px-4 py-2 hover:bg-gray-100 text-red-600">
Delete
</button>

</form>

</div>

</div>

</div>


{{-- MESSAGES --}}
<div id="chatBox"
class="flex-1 overflow-y-auto p-4 space-y-3
bg-[url('/img/whatsapp-bg.png')] bg-repeat">

@foreach($activeConversation->messages as $message)

<div
data-message-id="{{ $message->id }}"
class="flex {{ $message->direction === 'outgoing' ? 'justify-end' : 'justify-start' }}">

<div class="max-w-[80%] md:max-w-[65%]">

<div class="px-4 py-2 text-sm rounded-lg shadow break-words
{{ $message->direction === 'outgoing'
? 'bg-green-500 text-white rounded-br-none'
: 'bg-white text-gray-800 rounded-bl-none' }}">

{!! nl2br(e($message->content)) !!}

</div>

<div class="text-[11px] text-gray-500 mt-1
{{ $message->direction === 'outgoing' ? 'text-right' : '' }}">

{{ $message->created_at->format('H:i') }}

</div>

</div>

</div>

@endforeach

</div>


{{-- INPUT --}}
<div class="bg-[#f0f2f5] border-t p-3">

<form
method="POST"
action="{{ route('admin.inbox.reply',$activeConversation->id) }}"
enctype="multipart/form-data">

@csrf

<div class="flex items-center gap-2">

<input
type="text"
name="message"
placeholder="Type a message"
class="flex-1 bg-white rounded-full px-4 py-2 text-sm border border-gray-200 focus:ring-2 focus:ring-green-500">

<input type="file" name="attachment" class="hidden md:block text-xs">

<button
class="bg-green-500 hover:bg-green-600 text-white px-5 py-2 rounded-full text-sm">
Send
</button>

</div>

</form>

</div>

@endif

</div>

</div>


<script>

function toggleSidebar(){
document.getElementById('sidebar').classList.toggle('-translate-x-full');
}

function toggleMenu(){
document.getElementById('menu').classList.toggle('hidden');
}

const chatBox = document.getElementById("chatBox");

function scrollBottom(){
if(chatBox){
chatBox.scrollTop = chatBox.scrollHeight;
}
}

scrollBottom();

</script>

</x-app-layout>