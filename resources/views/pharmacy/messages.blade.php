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
        <div class="flex-1 overflow-y-auto custom-scrollbar">
            @if($conversations->isEmpty())
                <div class="p-8 text-center text-slate-500">
                    <i class="fas fa-inbox text-3xl text-slate-300 mb-3"></i>
                    <p class="font-medium text-sm">No messages yet</p>
                </div>
            @else
                @foreach($conversations as $conv)
                    <a href="{{ route('pharmacy.messages', $conv->id) }}" 
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
            <div class="flex-1 p-6 overflow-y-auto custom-scrollbar flex flex-col gap-4 bg-slate-50/50" id="chat-messages">
                @foreach($conversation->messages as $msg)
                    @php $isMine = $msg->sender_id === Auth::id(); @endphp
                    <div class="flex {{ $isMine ? 'justify-end' : 'justify-start' }}">
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
@if(isset($conversation))
<script>
    // Scroll to bottom on load
    const chatMessages = document.getElementById('chat-messages');
    chatMessages.scrollTop = chatMessages.scrollHeight;

    // Optional: Add basic AJAX submission for smooth feel
    document.getElementById('chat-form').addEventListener('submit', function(e) {
        // e.preventDefault(); 
        // Can implement AJAX here, but standard submit works fine for MVP
    });
</script>
@endif
@endpush
@endsection
