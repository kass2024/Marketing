<x-app-layout>

<div class="h-[88vh] bg-gray-100 p-6">

<div class="h-full bg-white rounded-2xl shadow-lg flex overflow-hidden">

{{-- LEFT SIDEBAR --}}
<div class="w-[340px] border-r flex flex-col">

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
<div class="px-4 py-3 flex gap-2 text-xs">

@foreach(['all','unread','human','bot','closed'] as $f)

<a
href="?filter={{ $f }}"
class="px-3 py-1 rounded-full font-medium transition
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
class="flex items-center gap-3 px-4 py-3 border-b hover:bg-gray-50 transition
{{ request('conversation') == $conversation->id ? 'bg-gray-100' : '' }}">

{{-- AVATAR --}}
<div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center font-semibold text-blue-600">
{{ strtoupper(substr($conversation->customer_name ?? 'U',0,1)) }}
</div>

<div class="flex-1">

<p class="text-sm font-semibold text-gray-800">
{{ $conversation->customer_name ?? $conversation->phone_number }}
</p>

<p class="text-xs text-gray-500 truncate">
{{ $conversation->customer_email }}
</p>

</div>

@if($conversation->unread_count > 0)
<div class="bg-red-500 text-white text-xs px-2 py-1 rounded-full">
{{ $conversation->unread_count }}
</div>
@endif

</a>

@endforeach

</div>

<div class="p-3 border-t">
{{ $conversations->links() }}
</div>

</div>



{{-- RIGHT CHAT AREA --}}
<div class="flex-1 flex flex-col bg-gray-50">

@if($activeConversation)

{{-- HEADER --}}
<div class="bg-white border-b px-6 py-4 flex justify-between items-center">

<div class="flex items-center gap-3">

<div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center font-semibold text-blue-600">
{{ strtoupper(substr($activeConversation->customer_name ?? 'U',0,1)) }}
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
<button class="px-4 py-2 rounded-lg text-sm font-medium
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
<button class="px-4 py-2 bg-red-500 text-white rounded-lg text-sm font-medium">
Close
</button>
</form>

</div>

</div>



{{-- MESSAGE THREADS --}}
<div class="flex-1 overflow-y-auto p-8 space-y-6">

@php
$messages = $activeConversation->messages;
@endphp

@for($i = 0; $i < count($messages); $i++)

@if($messages[$i]->direction === 'incoming')

<div class="bg-white border rounded-xl shadow-sm p-6">

{{-- QUESTION --}}
<div class="mb-4">

<div class="text-xs font-semibold text-gray-400 mb-1">
Customer Question
</div>

<div class="bg-gray-100 rounded-lg px-4 py-3 text-sm text-gray-800">
{{ $messages[$i]->content }}
</div>

<div class="text-xs text-gray-400 mt-1">
{{ $messages[$i]->created_at->format('H:i') }}
</div>

</div>


{{-- ANSWER --}}
@if(isset($messages[$i+1]) && $messages[$i+1]->direction === 'outgoing')

<div>

<div class="text-xs font-semibold text-blue-500 mb-1">
Your Reply
</div>

<div class="bg-blue-600 text-white rounded-lg px-4 py-3 text-sm">
{{ $messages[$i+1]->content }}
</div>

<div class="text-xs text-gray-300 mt-1">
{{ $messages[$i+1]->created_at->format('H:i') }}
</div>

</div>

@php $i++; @endphp

@endif

</div>

@endif

@endfor

</div>



{{-- REPLY BOX --}}
<div class="bg-white border-t p-4">

<form method="POST"
action="{{ route('admin.inbox.reply',$activeConversation->id) }}">

@csrf

<div class="flex gap-3">

<input
type="text"
name="message"
placeholder="Write your reply..."
class="flex-1 bg-gray-100 rounded-lg px-4 py-3 border-0 focus:ring-2 focus:ring-blue-500"
required>

<button
class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold">

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

</div>

</x-app-layout>