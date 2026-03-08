<x-app-layout>
<div class="h-screen bg-gray-100 flex overflow-hidden">
    {{-- ================= SIDEBAR ================= --}}
    <div id="sidebar" 
         class="w-[85%] sm:w-[340px] bg-white border-r flex flex-col
                fixed md:relative inset-y-0 left-0 z-30 md:z-20
                transform transition-transform duration-300 ease-in-out
                {{ request('conversation') ? '-translate-x-full md:translate-x-0' : 'translate-x-0' }}
                shadow-xl md:shadow-none">
        
        {{-- Sidebar Header --}}
        <div class="p-4 border-b flex justify-between items-center bg-white sticky top-0 z-10">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="md:hidden text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
                <h2 class="font-semibold text-gray-700 text-lg">Inbox</h2>
            </div>
            <a href="/admin/bulk" 
               class="text-xs bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-lg transition-colors">
                Bulk Send
            </a>
        </div>

        {{-- Search --}}
        <div class="p-4 border-b bg-white">
            <input type="text" 
                   name="search" 
                   value="{{ $search ?? '' }}" 
                   placeholder="Search conversations..." 
                   class="w-full bg-gray-100 rounded-lg px-4 py-2.5 border-0 focus:ring-2 focus:ring-blue-500 text-sm">
        </div>

        {{-- Filter Chips --}}
        <div class="px-4 py-3 flex gap-2 overflow-x-auto hide-scrollbar border-b bg-gray-50">
            @foreach(['all','unread','human','bot','closed'] as $f)
                <a href="?filter={{ $f }}" 
                   class="px-3 py-1.5 rounded-full font-medium text-sm whitespace-nowrap transition-colors
                          {{ ($filter ?? 'all') === $f 
                              ? 'bg-blue-600 text-white' 
                              : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200' }}">
                    {{ ucfirst($f) }}
                </a>
            @endforeach
        </div>

        {{-- Conversations List --}}
        <div class="flex-1 overflow-y-auto bg-white">
            @forelse($conversations ?? [] as $conversation)
                <a href="?conversation={{ $conversation->id }}" 
                   class="flex items-center gap-3 px-4 py-3 border-b hover:bg-gray-50 transition-colors
                          {{ request('conversation') == $conversation->id ? 'bg-blue-50 border-l-4 border-l-blue-600' : '' }}">
                    
                    {{-- Avatar --}}
                    <div class="relative flex-shrink-0">
                        <div class="w-12 h-12 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center font-semibold text-white text-sm">
                            {{ strtoupper(substr($conversation->customer_name ?? $conversation->phone_number ?? 'U', 0, 1)) }}
                        </div>
                        @if($conversation->is_online ?? false)
                            <div class="absolute bottom-0 right-0 w-3.5 h-3.5 bg-green-500 border-2 border-white rounded-full"></div>
                        @endif
                    </div>

                    {{-- Conversation Info --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-gray-800 truncate">
                                {{ $conversation->customer_name ?? $conversation->phone_number }}
                            </p>
                            @if($conversation->created_at ?? false)
                                <span class="text-xs text-gray-400 flex-shrink-0">
                                    {{ $conversation->created_at->format('H:i') }}
                                </span>
                            @endif
                        </div>
                        
                        <p class="text-xs text-gray-500 truncate mt-0.5">
                            {{ $conversation->customer_email ?? 'No email' }}
                        </p>
                        
                        @if($conversation->last_message ?? false)
                            <p class="text-xs text-gray-600 truncate mt-1">
                                {{ $conversation->last_message }}
                            </p>
                        @endif
                    </div>

                    {{-- Unread Badge & Status --}}
                    <div class="flex flex-col items-end gap-1 flex-shrink-0">
                        @if($conversation->status === 'human')
                            <span class="text-[10px] bg-yellow-500 text-white px-2 py-0.5 rounded-full whitespace-nowrap">
                                ESCALATED
                            </span>
                        @endif
                        
                        @if($conversation->unread_count > 0)
                            <span class="bg-red-500 text-white text-xs px-2 py-0.5 rounded-full min-w-[20px] text-center">
                                {{ $conversation->unread_count }}
                            </span>
                        @endif
                    </div>
                </a>
            @empty
                <div class="flex flex-col items-center justify-center py-12 px-4 text-center">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                    </div>
                    <h3 class="text-gray-700 font-medium mb-1">No conversations</h3>
                    <p class="text-gray-500 text-sm">Start a new conversation to get started</p>
                </div>
            @endforelse
        </div>

        {{-- Pagination --}}
        @if(($conversations ?? collect())->hasPages())
            <div class="p-3 border-t bg-white">
                {{ $conversations->links() }}
            </div>
        @endif
    </div>

    {{-- ================= CHAT AREA ================= --}}
    <div class="flex-1 flex flex-col h-full bg-gray-100 relative">
        @if($activeConversation ?? false)
            {{-- Chat Header --}}
            <div class="bg-white border-b px-4 py-2 flex justify-between items-center sticky top-0 z-20 shadow-sm">
                <div class="flex items-center gap-3 min-w-0">
                    <button onclick="toggleSidebar()" class="md:hidden text-gray-500 hover:text-gray-700 p-1">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>

                    <div class="relative flex-shrink-0">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center font-semibold text-white">
                            {{ strtoupper(substr($activeConversation->customer_name ?? $activeConversation->phone_number ?? 'U', 0, 1)) }}
                        </div>
                        <div id="onlineIndicator" 
                             class="absolute bottom-0 right-0 w-3 h-3 border-2 border-white rounded-full transition-colors
                                    {{ ($activeConversation->is_online ?? false) ? 'bg-green-500' : 'bg-gray-300' }}">
                        </div>
                    </div>

                    <div class="min-w-0">
                        <p class="font-semibold text-gray-800 truncate">
                            {{ $activeConversation->customer_name ?? $activeConversation->phone_number }}
                        </p>
                        <p class="text-xs text-gray-500 truncate">
                            {{ $activeConversation->customer_email ?? 'No email' }}
                        </p>
                    </div>
                </div>

                {{-- Action Buttons (Hidden on mobile, shown in dropdown) --}}
                <div class="hidden sm:flex gap-2">
                    <form method="POST" action="{{ route('admin.inbox.toggle', $activeConversation->id) }}">
                        @csrf
                        <button class="px-4 py-2 rounded-lg text-sm font-medium transition-colors
                                     {{ $activeConversation->status === 'bot' 
                                         ? 'bg-blue-600 hover:bg-blue-700 text-white' 
                                         : 'bg-yellow-500 hover:bg-yellow-600 text-white' }}">
                            {{ $activeConversation->status === 'bot' ? 'Switch to Human' : 'Switch to Bot' }}
                        </button>
                    </form>

                    <form method="POST" action="{{ route('admin.inbox.close', $activeConversation->id) }}">
                        @csrf
                        <button class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg text-sm font-medium transition-colors">
                            Close
                        </button>
                    </form>

                    <form method="POST" 
                          action="{{ route('admin.inbox.delete', $activeConversation->id) }}"
                          onsubmit="return confirm('Delete this conversation permanently?')">
                        @csrf
                        @method('DELETE')
                        <button class="px-4 py-2 bg-gray-800 hover:bg-gray-900 text-white rounded-lg text-sm font-medium transition-colors">
                            Delete
                        </button>
                    </form>
                </div>

                {{-- Mobile Actions Dropdown --}}
                <div class="sm:hidden relative" x-data="{ open: false }">
                    <button @click="open = !open" class="p-2 text-gray-500 hover:text-gray-700">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path>
                        </svg>
                    </button>
                    
                    <div x-show="open" 
                         @click.away="open = false"
                         class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border py-1 z-30">
                        <form method="POST" action="{{ route('admin.inbox.toggle', $activeConversation->id) }}" class="block">
                            @csrf
                            <button class="w-full text-left px-4 py-2 text-sm hover:bg-gray-50">
                                {{ $activeConversation->status === 'bot' ? 'Switch to Human' : 'Switch to Bot' }}
                            </button>
                        </form>
                        
                        <form method="POST" action="{{ route('admin.inbox.close', $activeConversation->id) }}" class="block">
                            @csrf
                            <button class="w-full text-left px-4 py-2 text-sm hover:bg-gray-50 text-red-600">
                                Close
                            </button>
                        </form>
                        
                        <form method="POST" 
                              action="{{ route('admin.inbox.delete', $activeConversation->id) }}"
                              onsubmit="return confirm('Delete this conversation permanently?')" 
                              class="block">
                            @csrf
                            @method('DELETE')
                            <button class="w-full text-left px-4 py-2 text-sm hover:bg-gray-50 text-red-600">
                                Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- ================= MESSAGE STREAM ================= --}}
            <div id="chatBox" 
                 class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-4 bg-[#e5ded8] bg-opacity-30"
                 style="background-image: url('data:image/svg+xml,%3Csvg width%3D%2260%22 height%3D%2260%22 viewBox%3D%220 0 60 60%22 xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Cg fill%3D%22none%22 fill-rule%3D%22evenodd%22%3E%3Cg fill%3D%22%239C92AC%22 fill-opacity%3D%220.05%22%3E%3Cpath d%3D%22M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z%22%2F%3E%3C%2Fg%3E%3C%2Fg%3E%3C%2Fsvg%3E')">

                @forelse($activeConversation->messages ?? [] as $message)
                    <div data-message-id="{{ $message->id }}" 
                         class="flex {{ $message->direction === 'outgoing' ? 'justify-end' : 'justify-start' }} animate-fade-in">
                        
                        <div class="max-w-[85%] sm:max-w-[70%]">
                            {{-- Message Content --}}
                            <div class="px-4 py-2.5 text-sm rounded-2xl shadow-sm break-words
                                      {{ $message->direction === 'outgoing' 
                                          ? 'bg-blue-600 text-white rounded-br-none' 
                                          : 'bg-white text-gray-800 rounded-bl-none' }}">
                                
                                @if($message->content)
                                    {!! nl2br(e($message->content)) !!}
                                @endif
                                
                                @if($message->attachment ?? false)
                                    <div class="mt-2 {{ $message->direction === 'outgoing' ? 'text-blue-100' : 'text-gray-600' }}">
                                        <a href="{{ $message->attachment_url }}" 
                                           target="_blank" 
                                           class="flex items-center gap-2 hover:underline">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                            </svg>
                                            <span class="text-xs">Attachment</span>
                                        </a>
                                    </div>
                                @endif
                            </div>

                            {{-- Message Status --}}
                            <div id="msg-status-{{ $message->id }}" 
                                 class="text-[11px] text-gray-500 mt-1 flex items-center gap-1 px-1
                                        {{ $message->direction === 'outgoing' ? 'justify-end' : '' }}">
                                
                                <span>{{ $message->created_at->format('H:i') }}</span>
                                
                                @if($message->direction === 'outgoing')
                                    @if($message->status === 'sent')
                                        <span>✓</span>
                                    @elseif($message->status === 'delivered')
                                        <span>✓✓</span>
                                    @elseif($message->status === 'read')
                                        <span class="text-blue-500">✓✓</span>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center h-full text-center">
                        <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center mb-4 shadow-sm">
                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                            </svg>
                        </div>
                        <h3 class="text-gray-700 font-medium mb-1">No messages yet</h3>
                        <p class="text-gray-500 text-sm">Send a message to start the conversation</p>
                    </div>
                @endforelse
            </div>

            {{-- ================= REPLY BOX ================= --}}
            <div class="bg-white border-t p-3 sm:p-4 sticky bottom-0 shadow-lg">
                <form method="POST" 
                      action="{{ route('admin.inbox.reply', $activeConversation->id) }}" 
                      enctype="multipart/form-data"
                      class="flex flex-col sm:flex-row gap-2">
                    
                    @csrf
                    
                    <div class="flex-1 flex items-center gap-2 bg-gray-100 rounded-full px-4 py-1">
                        <input type="text" 
                               name="message" 
                               placeholder="Type a message..." 
                               class="flex-1 bg-transparent border-0 focus:ring-0 text-sm py-3"
                               autocomplete="off">
                        
                        <label class="cursor-pointer text-gray-500 hover:text-gray-700">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                            </svg>
                            <input type="file" name="attachment" class="hidden">
                        </label>
                    </div>
                    
                    <button class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-full font-semibold text-sm transition-colors w-full sm:w-auto">
                        Send
                    </button>
                </form>
            </div>
        @else
            {{-- No Active Conversation --}}
            <div class="flex-1 flex items-center justify-center p-6">
                <div class="text-center max-w-sm">
                    <div class="w-20 h-20 bg-gray-200 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                    </div>
                    <h3 class="text-gray-700 font-medium text-lg mb-2">Select a conversation</h3>
                    <p class="text-gray-500">Choose a conversation from the sidebar to start chatting</p>
                    <button onclick="toggleSidebar()" class="mt-4 md:hidden bg-blue-600 text-white px-6 py-2 rounded-lg text-sm">
                        Show conversations
                    </button>
                </div>
            </div>
        @endif
    </div>

    {{-- Overlay for mobile sidebar --}}
    <div id="sidebarOverlay" 
         onclick="toggleSidebar()"
         class="fixed inset-0 bg-black bg-opacity-50 z-20 md:hidden hidden transition-opacity"
         style="display: {{ request('conversation') ? 'none' : 'block' }};">
    </div>
</div>

<style>
    .hide-scrollbar::-webkit-scrollbar {
        display: none;
    }
    .hide-scrollbar {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
    
    @keyframes fade-in {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .animate-fade-in {
        animation: fade-in 0.3s ease-out;
    }
</style>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById("sidebar");
        const overlay = document.getElementById("sidebarOverlay");
        
        sidebar.classList.toggle("-translate-x-full");
        sidebar.classList.toggle("translate-x-0");
        
        if (overlay) {
            if (sidebar.classList.contains("-translate-x-full")) {
                overlay.style.display = "none";
            } else {
                overlay.style.display = "block";
            }
        }
    }

    // Auto-hide sidebar on mobile when conversation is selected
    window.addEventListener('load', function() {
        if (window.innerWidth < 768 && {{ request('conversation') ? 'true' : 'false' }}) {
            const sidebar = document.getElementById("sidebar");
            const overlay = document.getElementById("sidebarOverlay");
            
            sidebar.classList.add("-translate-x-full");
            sidebar.classList.remove("translate-x-0");
            if (overlay) overlay.style.display = "none";
        }
    });

    // Chat scrolling functionality
    const chatBox = document.getElementById("chatBox");
    
    function isAtBottom() {
        if (!chatBox) return true;
        return chatBox.scrollHeight - chatBox.scrollTop - chatBox.clientHeight < 100;
    }
    
    function scrollToBottom() {
        if (chatBox) {
            chatBox.scrollTop = chatBox.scrollHeight;
        }
    }
    
    if (chatBox) {
        scrollToBottom();
    }

    // Auto-refresh messages
    @if($activeConversation ?? false)
        const conversationId = {{ $activeConversation->id }};
        let lastMessageId = document.querySelector('[data-message-id]:last-child')?.dataset.messageId || 0;
        
        function fetchMessages() {
            const shouldScroll = isAtBottom();
            
            fetch(`/admin/inbox/${conversationId}/messages`)
                .then(res => res.json())
                .then(data => {
                    const messages = data.messages || [];
                    const online = data.online;
                    
                    updateOnlineStatus(online);
                    
                    messages.forEach(msg => {
                        updateMessageStatus(msg);
                        
                        if (msg.id > lastMessageId) {
                            appendNewMessage(msg);
                            lastMessageId = msg.id;
                        }
                    });
                    
                    if (shouldScroll) scrollToBottom();
                })
                .catch(error => console.error('Error fetching messages:', error));
        }
        
        function updateOnlineStatus(online) {
            const dot = document.getElementById("onlineIndicator");
            if (!dot) return;
            
            dot.classList.remove("bg-green-500", "bg-gray-300");
            dot.classList.add(online ? "bg-green-500" : "bg-gray-300");
        }
        
        function updateMessageStatus(msg) {
            if (!msg.status) return;
            
            const statusEl = document.getElementById("msg-status-" + msg.id);
            if (!statusEl) return;
            
            let ticks = "";
            if (msg.status === "sent") ticks = "✓";
            if (msg.status === "delivered") ticks = "✓✓";
            if (msg.status === "read") ticks = '<span class="text-blue-500">✓✓</span>';
            
            statusEl.innerHTML = msg.time + " " + ticks;
        }
        
        function appendNewMessage(msg) {
            if (!chatBox) return;
            
            let content = msg.content ? `<div class="break-words">${msg.content.replace(/\n/g, '<br>')}</div>` : "";
            
            if (msg.attachment) {
                content += `<div class="mt-2 ${msg.direction === 'outgoing' ? 'text-blue-100' : 'text-gray-600'}">
                    <a href="${msg.attachment_url}" target="_blank" class="flex items-center gap-2 hover:underline">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                        </svg>
                        <span class="text-xs">Attachment</span>
                    </a>
                </div>`;
            }
            
            const wrapper = document.createElement("div");
            wrapper.className = "flex " + (msg.direction === "outgoing" ? "justify-end" : "justify-start") + " animate-fade-in";
            wrapper.setAttribute("data-message-id", msg.id);
            
            wrapper.innerHTML = `
                <div class="max-w-[85%] sm:max-w-[70%]">
                    <div class="px-4 py-2.5 text-sm rounded-2xl shadow-sm
                              ${msg.direction === 'outgoing' 
                                  ? 'bg-blue-600 text-white rounded-br-none' 
                                  : 'bg-white text-gray-800 rounded-bl-none'}">
                        ${content}
                    </div>
                    <div id="msg-status-${msg.id}" class="text-[11px] text-gray-500 mt-1 px-1 ${msg.direction === 'outgoing' ? 'text-right' : ''}">
                        ${msg.time}
                    </div>
                </div>
            `;
            
            chatBox.appendChild(wrapper);
        }
        
        // Start polling
        setInterval(fetchMessages, 3000);
    @endif

    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 768) {
            const sidebar = document.getElementById("sidebar");
            const overlay = document.getElementById("sidebarOverlay");
            
            sidebar.classList.remove("-translate-x-full");
            sidebar.classList.add("translate-x-0");
            if (overlay) overlay.style.display = "none";
        }
    });
</script>

{{-- Alpine.js for mobile dropdown --}}
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</x-app-layout>