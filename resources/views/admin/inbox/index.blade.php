<x-app-layout>

<div class="h-[85vh] flex bg-white rounded-2xl shadow overflow-hidden">

    {{-- LEFT: Conversation List --}}
    <div class="w-1/3 border-r flex flex-col">

        {{-- Search + Filters --}}
        <div class="p-4 border-b space-y-3">

            <form method="GET">
                <input type="text"
                       name="search"
                       value="{{ $search }}"
                       placeholder="Search name, email, phone..."
                       class="w-full border rounded-lg px-3 py-2">
            </form>

            <div class="flex gap-2 text-sm">
                @foreach(['all','unread','human','bot','closed'] as $f)
                    <a href="?filter={{ $f }}"
                       class="px-3 py-1 rounded-full
                       {{ $filter === $f ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600' }}">
                        {{ ucfirst($f) }}
                    </a>
                @endforeach
            </div>

        </div>

        {{-- Conversations --}}
        <div class="flex-1 overflow-y-auto">

            @foreach($conversations as $conversation)
                <a href="?conversation={{ $conversation->id }}"
                   class="block p-4 border-b hover:bg-gray-50
                   {{ request('conversation') == $conversation->id ? 'bg-gray-100' : '' }}">

                    <div class="flex justify-between">
                        <div>
                            <p class="font-semibold">
                                {{ $conversation->customer_name ?? $conversation->phone_number }}
                            </p>
                            <p class="text-xs text-gray-500">
                                {{ $conversation->customer_email }}
                            </p>
                        </div>

                        @if($conversation->unread_count > 0)
                            <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full">
                                {{ $conversation->unread_count }}
                            </span>
                        @endif
                    </div>

                </a>
            @endforeach

        </div>

        <div class="p-4">
            {{ $conversations->links() }}
        </div>

    </div>

    {{-- RIGHT: Chat Area --}}
    <div class="flex-1 flex flex-col">

        @if($activeConversation)

            {{-- Header --}}
            <div class="p-4 border-b flex justify-between items-center">
                <div>
                    <h2 class="font-bold text-lg">
                        {{ $activeConversation->customer_name }}
                    </h2>
                    <p class="text-sm text-gray-500">
                        {{ $activeConversation->customer_email }}
                    </p>
                </div>

                <div class="flex gap-2">

                    <form method="POST" action="{{ route('admin.inbox.toggle',$activeConversation->id) }}">
                        @csrf
                        <button class="px-3 py-1 rounded-lg text-sm
                            {{ $activeConversation->status === 'bot' ? 'bg-blue-600 text-white' : 'bg-yellow-500 text-white' }}">
                            {{ $activeConversation->status === 'bot' ? 'Switch to Human' : 'Switch to Bot' }}
                        </button>
                    </form>

                    <form method="POST" action="{{ route('admin.inbox.close',$activeConversation->id) }}">
                        @csrf
                        <button class="px-3 py-1 bg-red-500 text-white rounded-lg text-sm">
                            Close
                        </button>
                    </form>

                </div>
            </div>

            {{-- Messages --}}
            <div class="flex-1 overflow-y-auto p-6 bg-gray-50 space-y-4">

                @foreach($activeConversation->messages as $message)
                    <div class="{{ $message->direction === 'outgoing' ? 'text-right' : '' }}">
                        <div class="inline-block px-4 py-2 rounded-xl
                            {{ $message->direction === 'outgoing'
                                ? 'bg-blue-600 text-white'
                                : 'bg-gray-200 text-gray-800' }}">
                            {{ $message->content }}
                        </div>
                        <div class="text-xs text-gray-400 mt-1">
                            {{ $message->created_at->format('H:i') }}
                        </div>
                    </div>
                @endforeach

            </div>

            {{-- Reply --}}
            <div class="p-4 border-t">
                <form method="POST"
                      action="{{ route('admin.inbox.reply',$activeConversation->id) }}">
                    @csrf
                    <div class="flex gap-3">
                        <input type="text"
                               name="message"
                               class="flex-1 border rounded-lg px-4 py-2"
                               placeholder="Type your reply..." required>
                        <button class="bg-blue-600 text-white px-6 py-2 rounded-lg">
                            Send
                        </button>
                    </div>
                </form>
            </div>

        @else
            <div class="flex-1 flex items-center justify-center text-gray-400">
                Select a conversation
            </div>
        @endif

    </div>

</div>

</x-app-layout>