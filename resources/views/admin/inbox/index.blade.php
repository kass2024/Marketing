<x-app-layout>
<div class="h-screen bg-gray-100 flex overflow-hidden">
    {{-- ================= SIDEBAR ================= --}}
    <div id="sidebar" 
         class="w-full md:w-[380px] bg-white border-r flex flex-col
                fixed md:relative inset-y-0 left-0 z-30 md:z-20
                transform transition-transform duration-300 ease-in-out
                {{ request('conversation') ? '-translate-x-full md:translate-x-0' : 'translate-x-0' }}
                shadow-xl md:shadow-none">
        
        {{-- Sidebar Header --}}
        <div class="p-3 border-b bg-white sticky top-0 z-10">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <button onclick="toggleSidebar()" class="md:hidden text-gray-600 hover:text-gray-800">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                    <h1 class="text-xl font-bold text-gray-800">WhatsApp</h1>
                </div>
                <div class="flex items-center gap-4">
                    <a href="/admin/bulk" class="text-gray-600 hover:text-gray-800">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                        </svg>
                    </a>
                    <button class="text-gray-600 hover:text-gray-800">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        {{-- Search --}}
        <div class="p-2 bg-white">
            <div class="bg-gray-100 rounded-lg flex items-center px-3 py-2">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
                <input type="text" 
                       name="search" 
                       value="{{ $search ?? '' }}" 
                       placeholder="Search conversations..." 
                       class="w-full bg-transparent border-0 focus:ring-0 text-sm px-2">
            </div>
        </div>

        {{-- Filter Tabs --}}
        <div class="flex border-b bg-white">
            @foreach(['all','unread','human','bot','closed'] as $f)
                <a href="?filter={{ $f }}" 
                   class="flex-1 text-center py-3 text-sm font-medium transition-colors
                          {{ ($filter ?? 'all') === $f 
                              ? 'text-blue-600 border-b-2 border-blue-600' 
                              : 'text-gray-600 hover:text-gray-800' }}">
                    {{ ucfirst($f) }}
                </a>
            @endforeach
        </div>

        {{-- Conversations List --}}
        <div class="flex-1 overflow-y-auto bg-white">
            @forelse($conversations ?? [] as $conversation)
                <a href="?conversation={{ $conversation->id }}" 
                   class="flex items-center gap-3 px-3 py-3 border-b hover:bg-gray-50 transition-colors
                          {{ request('conversation') == $conversation->id ? 'bg-gray-100' : '' }}">
                    
                    {{-- Avatar --}}
                    <div class="relative flex-shrink-0">
                        <div class="w-12 h-12 rounded-full bg-gray-300 flex items-center justify-center font-semibold text-gray-700">
                            {{ strtoupper(substr($conversation->customer_name ?? $conversation->phone_number ?? 'U', 0, 1)) }}
                        </div>
                        @if($conversation->is_online ?? false)
                            <div class="absolute bottom-0 right-0 w-3.5 h-3.5 bg-green-500 border-2 border-white rounded-full"></div>
                        @endif
                    </div>

                    {{-- Conversation Info --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <p class="font-semibold text-gray-800 truncate">
                                {{ $conversation->customer_name ?? $conversation->phone_number }}
                            </p>
                            @if($conversation->created_at ?? false)
                                <span class="text-xs text-gray-500 flex-shrink-0">
                                    {{ $conversation->created_at->format('H:i') }}
                                </span>
                            @endif
                        </div>
                        
                        <p class="text-xs text-gray-600 truncate mt-0.5">
                            @if($conversation->status === 'human')
                                <span class="text-yellow-600">👤 </span>
                            @elseif($conversation->status === 'bot')
                                <span class="text-blue-600">🤖 </span>
                            @endif
                            {{ $conversation->last_message ?? 'No messages yet' }}
                        </p>
                    </div>

                    {{-- Unread Badge --}}
                    @if($conversation->unread_count > 0)
                        <span class="bg-green-500 text-white text-xs px-2 py-1 rounded-full min-w-[20px] text-center">
                            {{ $conversation->unread_count }}
                        </span>
                    @endif
                </a>
            @empty
                <div class="flex flex-col items-center justify-center py-12 px-4 text-center">
                    <div class="w-16 h-16 bg-gray-200 rounded-full flex items-center justify-center mb-4">
                        <svg class="w-8 h-8 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
            <div class="bg-gray-100 px-4 py-2 flex justify-between items-center sticky top-0 z-20 border-b">
                <div class="flex items-center gap-3 min-w-0">
                    <button onclick="toggleSidebar()" class="md:hidden text-gray-600 hover:text-gray-800 p-1">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>

                    <div class="relative flex-shrink-0">
                        <div class="w-10 h-10 rounded-full bg-gray-300 flex items-center justify-center font-semibold text-gray-700">
                            {{ strtoupper(substr($activeConversation->customer_name ?? $activeConversation->phone_number ?? 'U', 0, 1)) }}
                        </div>
                        <div id="onlineIndicator" 
                             class="absolute bottom-0 right-0 w-3 h-3 border-2 border-white rounded-full transition-colors
                                    {{ ($activeConversation->is_online ?? false) ? 'bg-green-500' : 'bg-gray-400' }}">
                        </div>
                    </div>

                    <div class="min-w-0">
                        <p class="font-semibold text-gray-800 truncate">
                            {{ $activeConversation->customer_name ?? $activeConversation->phone_number }}
                        </p>
                        <p id="onlineStatus" class="text-xs text-gray-600">
                            {{ ($activeConversation->is_online ?? false) ? 'Online' : 'Offline' }}
                        </p>
                    </div>
                </div>

                {{-- 3-dots Menu --}}
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" class="p-2 text-gray-600 hover:text-gray-800 rounded-full hover:bg-gray-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path>
                        </svg>
                    </button>
                    
                    <div x-show="open" 
                         @click.away="open = false"
                         x-transition
                         class="absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-lg border py-1 z-30">
                        
                        <form method="POST" action="{{ route('admin.inbox.toggle', $activeConversation->id) }}">
                            @csrf
                            <button class="w-full text-left px-4 py-2.5 text-sm hover:bg-gray-50 flex items-center gap-3">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                </svg>
                                {{ $activeConversation->status === 'bot' ? 'Switch to Human' : 'Switch to Bot' }}
                            </button>
                        </form>
                        
                        <form method="POST" action="{{ route('admin.inbox.close', $activeConversation->id) }}">
                            @csrf
                            <button class="w-full text-left px-4 py-2.5 text-sm hover:bg-gray-50 flex items-center gap-3">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                                Close Conversation
                            </button>
                        </form>
                        
                        <form method="POST" 
                              action="{{ route('admin.inbox.delete', $activeConversation->id) }}"
                              onsubmit="return confirm('Delete this conversation permanently?')">
                            @csrf
                            @method('DELETE')
                            <button class="w-full text-left px-4 py-2.5 text-sm hover:bg-gray-50 text-red-600 flex items-center gap-3">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                                Delete Conversation
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- ================= MESSAGE STREAM ================= --}}
            <div id="chatBox" 
                 class="flex-1 overflow-y-auto p-4 space-y-2 bg-[#e5ded8]"
                 style="background-image: url('data:image/svg+xml,%3Csvg width%3D%2252%22 height%3D%2252%22 viewBox%3D%220 0 52 52%22 xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Cg fill%3D%22none%22 fill-rule%3D%22evenodd%22%3E%3Cg fill%3D%22%23999%22 fill-opacity%3D%220.05%22%3E%3Cpath d%3D%22M32 32v-4h-4v4h4zm8 0v-4h-4v4h4zm-16 0v-4h-4v4h4zm-8 0v-4H8v4h4zm24-8v-4h-4v4h4zm8 0v-4h-4v4h4zm-16 0v-4h-4v4h4zm-8 0v-4H8v4h4zm24-8v-4h-4v4h4zm8 0v-4h-4v4h4zM32 8v-4h-4v4h4zm8 0v-4h-4v4h4zM24 8v-4h-4v4h4zm-8 0v-4H8v4h4z%22%2F%3E%3C%2Fg%3E%3C%2Fg%3E%3C%2Fsvg%3E')">

                @forelse($activeConversation->messages ?? [] as $message)
                    <div data-message-id="{{ $message->id }}" 
                         class="flex {{ $message->direction === 'outgoing' ? 'justify-end' : 'justify-start' }} message-item">
                        
                        <div class="max-w-[85%] sm:max-w-[65%]">
                            {{-- Message Content --}}
                            <div class="px-3 py-2 text-sm shadow-sm relative
                                      {{ $message->direction === 'outgoing' 
                                          ? 'bg-[#dcf8c6] text-gray-800 rounded-lg rounded-br-none' 
                                          : 'bg-white text-gray-800 rounded-lg rounded-bl-none' }}">
                                
                                @if($message->content)
                                    {!! nl2br(e($message->content)) !!}
                                @endif
                                
                                @if($message->attachment ?? false)
                                    <div class="mt-1 text-blue-600">
                                        <a href="{{ $message->attachment_url }}" 
                                           target="_blank" 
                                           class="flex items-center gap-1 hover:underline text-xs">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                            </svg>
                                            <span>Attachment</span>
                                        </a>
                                    </div>
                                @endif
                            </div>

                            {{-- Message Time & Status --}}
                            <div id="msg-status-{{ $message->id }}" 
                                 class="text-[10px] text-gray-600 mt-0.5 flex items-center gap-0.5 px-1
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
                        <p class="text-gray-500 text-sm">No messages yet. Start a conversation!</p>
                    </div>
                @endforelse
            </div>

            {{-- ================= REPLY BOX ================= --}}
            <div class="bg-gray-100 px-4 py-3 sticky bottom-0">
                <form method="POST" 
                      action="{{ route('admin.inbox.reply', $activeConversation->id) }}" 
                      enctype="multipart/form-data"
                      class="flex items-center gap-2">
                    
                    @csrf
                    
                    <label class="cursor-pointer text-gray-600 hover:text-gray-800">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                        </svg>
                        <input type="file" name="attachment" class="hidden">
                    </label>
                    
                    <input type="text" 
                           name="message" 
                           placeholder="Type a message..." 
                           class="flex-1 bg-white rounded-lg px-4 py-2.5 border-0 focus:ring-2 focus:ring-green-500 text-sm"
                           autocomplete="off">
                    
                    <button class="text-gray-600 hover:text-gray-800 p-2">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                        </svg>
                    </button>
                </form>
            </div>
        @else
            {{-- No Active Conversation - WhatsApp like landing --}}
            <div class="flex-1 flex flex-col items-center justify-center p-6 bg-gray-100">
                <div class="w-64 h-64 mb-6">
                    <img src="https://static.whatsapp.net/rsrc.php/v3/yz/r/ujTY9i_Jhs1.png" alt="WhatsApp" class="w-full h-full object-contain">
                </div>
                <h2 class="text-3xl font-light text-gray-600 mb-2">WhatsApp Web</h2>
                <p class="text-sm text-gray-500 text-center max-w-md">
                    Send and receive messages without keeping your phone online.
                    Use WhatsApp on up to 4 linked devices.
                </p>
                <button onclick="toggleSidebar()" class="mt-4 md:hidden bg-green-500 text-white px-6 py-2 rounded-lg text-sm">
                    Show conversations
                </button>
            </div>
        @endif
    </div>

    {{-- Overlay for mobile sidebar --}}
    <div id="sidebarOverlay" 
         onclick="toggleSidebar()"
         class="fixed inset-0 bg-black bg-opacity-40 z-20 md:hidden hidden transition-opacity"
         style="display: {{ request('conversation') ? 'none' : 'block' }};">
    </div>
</div>

<style>
    .message-item {
        animation: fadeIn 0.2s ease-out;
    }
    
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(5px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Custom scrollbar */
    .overflow-y-auto::-webkit-scrollbar {
        width: 6px;
    }
    
    .overflow-y-auto::-webkit-scrollbar-track {
        background: transparent;
    }
    
    .overflow-y-auto::-webkit-scrollbar-thumb {
        background: #ccc;
        border-radius: 3px;
    }
    
    .overflow-y-auto::-webkit-scrollbar-thumb:hover {
        background: #999;
    }
</style>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById("sidebar");
        const overlay = document.getElementById("sidebarOverlay");
        
        sidebar.classList.toggle("-translate-x-full");
        sidebar.classList.toggle("translate-x-0");
        
        if (overlay) {
            overlay.style.display = sidebar.classList.contains("-translate-x-full") ? "none" : "block";
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

    // Chat scrolling
    const chatBox = document.getElementById("chatBox");
    
    function isAtBottom() {
        if (!chatBox) return true;
        return chatBox.scrollHeight - chatBox.scrollTop - chatBox.clientHeight < 50;
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
                    
                    // Update online status
                    const dot = document.getElementById("onlineIndicator");
                    const status = document.getElementById("onlineStatus");
                    if (dot && status) {
                        dot.className = `absolute bottom-0 right-0 w-3 h-3 border-2 border-white rounded-full transition-colors ${online ? 'bg-green-500' : 'bg-gray-400'}`;
                        status.textContent = online ? 'Online' : 'Offline';
                    }
                    
                    messages.forEach(msg => {
                        const statusEl = document.getElementById("msg-status-" + msg.id);
                        if (statusEl && msg.status) {
                            let ticks = "";
                            if (msg.status === "sent") ticks = "✓";
                            if (msg.status === "delivered") ticks = "✓✓";
                            if (msg.status === "read") ticks = '<span class="text-blue-500">✓✓</span>';
                            statusEl.innerHTML = msg.time + " " + ticks;
                        }
                        
                        if (msg.id > lastMessageId) {
                            // Append new message
                            const wrapper = document.createElement("div");
                            wrapper.className = `flex ${msg.direction === "outgoing" ? "justify-end" : "justify-start"} message-item`;
                            wrapper.setAttribute("data-message-id", msg.id);
                            
                            let content = msg.content ? `<div>${msg.content.replace(/\n/g, '<br>')}</div>` : "";
                            
                            if (msg.attachment) {
                                content += `<div class="mt-1 text-blue-600">
                                    <a href="${msg.attachment_url}" target="_blank" class="flex items-center gap-1 hover:underline text-xs">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                        </svg>
                                        <span>Attachment</span>
                                    </a>
                                </div>`;
                            }
                            
                            wrapper.innerHTML = `
                                <div class="max-w-[85%] sm:max-w-[65%]">
                                    <div class="px-3 py-2 text-sm shadow-sm relative
                                              ${msg.direction === 'outgoing' 
                                                  ? 'bg-[#dcf8c6] text-gray-800 rounded-lg rounded-br-none' 
                                                  : 'bg-white text-gray-800 rounded-lg rounded-bl-none'}">
                                        ${content}
                                    </div>
                                    <div id="msg-status-${msg.id}" class="text-[10px] text-gray-600 mt-0.5 px-1 ${msg.direction === 'outgoing' ? 'text-right' : ''}">
                                        ${msg.time}
                                    </div>
                                </div>
                            `;
                            
                            chatBox.appendChild(wrapper);
                            lastMessageId = msg.id;
                        }
                    });
                    
                    if (shouldScroll) scrollToBottom();
                })
                .catch(error => console.error('Error:', error));
        }
        
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

{{-- Alpine.js for dropdown --}}
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</x-app-layout>