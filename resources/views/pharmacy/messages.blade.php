@extends('pharmacy.layouts.app')

@section('title', 'Messages')

@section('content')
<div class="mb-8 flex flex-col sm:flex-row sm:items-end justify-between gap-4">
    <div>
        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-1">Customer Support</p>
        <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">Messages</h2>
        <p class="text-slate-500 mt-1 text-sm font-normal">Chat with customers regarding their prescriptions and orders.</p>
    </div>
</div>

<div class="flex h-[700px] bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
    <!-- Sidebar / Conversation List -->
    <div class="w-1/3 border-r border-slate-200/80 flex flex-col bg-slate-50/50">
        <div class="p-4 border-b border-slate-200/80 bg-white">
            <h3 class="font-bold text-base text-slate-900">Conversations</h3>
        </div>
        <div class="flex-1 overflow-y-auto custom-scrollbar" id="conversations-list">
            @if($conversations->isEmpty())
                <div class="p-8 text-center text-slate-500" id="no-conversations-placeholder">
                    <i class="fas fa-inbox text-3xl text-slate-300 mb-3"></i>
                    <p class="font-medium text-sm">No messages yet</p>
                </div>
            @else
                @foreach($conversations as $conv)
                    <a href="{{ route('pharmacy.messages', $conv->id) }}" 
                       data-conv-id="{{ $conv->id }}"
                       class="block p-4 border-b border-slate-100 transition-colors {{ isset($conversation) && $conversation->id == $conv->id ? 'bg-[#E6F4EA]/60 border-l-4 border-l-[#0D7A5F]' : 'hover:bg-white' }}">
                        <div class="flex justify-between items-start mb-1">
                            <h4 class="font-bold text-slate-900 text-sm line-clamp-1">{{ $conv->user->name }}</h4>
                            @if($conv->latestMessage)
                                <span class="text-[10px] text-slate-400 whitespace-nowrap ml-2">{{ $conv->latestMessage->created_at->shortAbsoluteDiffForHumans() }}</span>
                            @endif
                        </div>
                        @if($conv->order_id)
                            <span class="inline-block px-2 py-0.5 bg-slate-100 text-slate-600 text-[10px] font-bold rounded mb-1">Order #{{ $conv->order_id }}</span>
                        @endif
                        @if($conv->latestMessage)
                            <p class="text-xs text-slate-500 line-clamp-1 {{ $conv->unreadCountFor(Auth::id()) > 0 ? 'font-bold text-slate-900' : '' }}">
                                {{ $conv->latestMessage->sender_id === Auth::id() ? 'You: ' : '' }}{{ $conv->latestMessage->body }}
                            </p>
                        @endif
                    </a>
                @endforeach
            @endif
        </div>
    </div>

    <!-- Chat Area -->
    <div class="w-2/3 flex flex-col bg-white">
        @if(isset($conversation))
            <!-- Header -->
            <div class="p-4 border-b border-slate-200/80 flex justify-between items-center bg-white shadow-2xs z-10">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-[#E6F4EA] text-[#0D7A5F] border border-emerald-100 rounded-full flex items-center justify-center font-bold text-sm">
                        {{ substr($conversation->user->name, 0, 1) }}
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 text-sm sm:text-base">{{ $conversation->user->name }}</h3>
                        @if($conversation->order)
                            <a href="{{ route('pharmacy.orders', ['status' => 'all']) }}" class="text-xs text-[#0D7A5F] hover:underline font-semibold">View Order #{{ $conversation->order->id }}</a>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Messages List -->
            <div class="flex-1 p-6 overflow-y-auto custom-scrollbar flex flex-col gap-4 bg-slate-50/50" id="chat-messages" data-current-conv="{{ $conversation->id }}">
                @foreach($conversation->messages as $msg)
                    @php $isMine = $msg->sender_id === Auth::id(); @endphp
                    <div class="flex {{ $isMine ? 'justify-end' : 'justify-start' }}" data-msg-id="{{ $msg->id }}">
                        <div class="max-w-[70%] {{ $isMine ? 'bg-[#0D7A5F] text-white rounded-2xl rounded-tr-xs' : 'bg-white border border-slate-200 text-slate-800 rounded-2xl rounded-tl-xs shadow-2xs' }} px-4 py-3">
                            <p class="text-sm leading-relaxed">{{ $msg->body }}</p>
                            @if($msg->image_path)
                                <img src="{{ asset('storage/' . $msg->image_path) }}" class="mt-2 rounded-lg max-h-48 object-cover">
                            @endif
                            <p class="text-[9px] mt-1 text-right {{ $isMine ? 'text-teal-100' : 'text-slate-400' }}">{{ $msg->created_at->format('h:i A') }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Input Area -->
            <div class="p-4 border-t border-slate-200/80 bg-white">
                <form action="{{ route('pharmacy.messages.send', $conversation->id) }}" method="POST" enctype="multipart/form-data" class="flex items-center gap-2" id="chat-form">
                    @csrf
                    <label class="cursor-pointer p-3 text-slate-400 hover:text-[#0D7A5F] transition-colors rounded-xl hover:bg-slate-50" title="Attach image">
                        <i class="fas fa-image text-lg"></i>
                        <input type="file" name="image" class="hidden" accept="image/*">
                    </label>
                    <input type="text" name="message" class="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#0D7A5F] focus:ring-2 focus:ring-[#0D7A5F]/20 transition-all text-slate-800 placeholder:text-slate-400" placeholder="Type your message..." required autocomplete="off" autofocus>
                    <button type="submit" class="p-3 bg-[#0D7A5F] hover:bg-[#0B6E53] text-white rounded-xl shadow-xs transition-colors flex items-center justify-center w-12 h-12 shrink-0 active:scale-95" title="Send Message">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </form>
            </div>
        @else
            <!-- No Conversation Selected -->
            <div class="flex-1 flex flex-col items-center justify-center text-slate-400">
                <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mb-3">
                    <i class="fas fa-comments text-2xl text-slate-400"></i>
                </div>
                <p class="font-medium text-sm text-slate-500">Select a conversation to start messaging</p>
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const chatMessages = document.getElementById('chat-messages');
    let isPolling = false;
    let pollInterval = null;

    if (chatMessages) {
        const activeConvId = chatMessages.getAttribute('data-current-conv');

        // Scroll to bottom on initial load
        chatMessages.scrollTop = chatMessages.scrollHeight;

        // Auto-refresh messages for active conversation
        const pollActiveMessages = async () => {
            if (isPolling || document.hidden) return;
            isPolling = true;

            try {
                const response = await fetch(window.location.href, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                // Guard against stale thread response if user switched conversations
                if (chatMessages.getAttribute('data-current-conv') !== activeConvId) {
                    return;
                }

                if (response.ok) {
                    const data = await response.json();
                    if (data && Array.isArray(data.messages)) {
                        let hasNew = false;
                        const isNearBottom = chatMessages.scrollHeight - chatMessages.scrollTop - chatMessages.clientHeight < 120;

                        data.messages.forEach(msg => {
                            if (!document.querySelector(`[data-msg-id="${msg.id}"]`)) {
                                hasNew = true;
                                const row = document.createElement('div');
                                row.className = 'flex ' + (msg.is_mine ? 'justify-end' : 'justify-start');
                                row.setAttribute('data-msg-id', msg.id);

                                const bubble = document.createElement('div');
                                bubble.className = 'max-w-[70%] ' + (msg.is_mine 
                                    ? 'bg-[#0D7A5F] text-white rounded-2xl rounded-tr-xs' 
                                    : 'bg-white border border-slate-200 text-slate-800 rounded-2xl rounded-tl-xs shadow-2xs') + ' px-4 py-3';

                                const bodyP = document.createElement('p');
                                bodyP.className = 'text-sm leading-relaxed';
                                bodyP.textContent = msg.body;
                                bubble.appendChild(bodyP);

                                if (msg.image_path) {
                                    const img = document.createElement('img');
                                    img.src = msg.image_path;
                                    img.className = 'mt-2 rounded-lg max-h-48 object-cover';
                                    bubble.appendChild(img);
                                }

                                const timeP = document.createElement('p');
                                timeP.className = 'text-[9px] mt-1 text-right ' + (msg.is_mine ? 'text-teal-100' : 'text-slate-400');
                                timeP.textContent = msg.time || msg.time_ago || '';
                                bubble.appendChild(timeP);

                                row.appendChild(bubble);
                                chatMessages.appendChild(row);

                                // Update sidebar preview for this conversation
                                const sideItem = document.querySelector(`[data-conv-id="${activeConvId}"]`);
                                if (sideItem) {
                                    const previewP = sideItem.querySelector('p.text-xs');
                                    if (previewP) {
                                        previewP.textContent = (msg.is_mine ? 'You: ' : '') + msg.body;
                                    }
                                }
                            }
                        });

                        if (hasNew && isNearBottom) {
                            chatMessages.scrollTop = chatMessages.scrollHeight;
                        }
                    }
                }
            } catch (err) {
                // Ignore transient network errors during background poll
            } finally {
                isPolling = false;
            }
        };

        // Start 10-second polling interval
        pollInterval = setInterval(pollActiveMessages, 10000);

        // Resume immediately when tab becomes visible
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                pollActiveMessages();
            }
        });
    }

    // Cleanup timers on unload
    window.addEventListener('beforeunload', function() {
        if (pollInterval) clearInterval(pollInterval);
    });
});
</script>
@endpush
@endsection
