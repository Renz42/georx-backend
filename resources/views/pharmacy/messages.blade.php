@extends('pharmacy.layouts.app')

@section('title', 'Messages')

@section('content')
<div class="mb-8 flex flex-col sm:flex-row sm:items-end justify-between gap-4">
    <div>
        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Customer Support</p>
        <h2 class="text-3xl font-black text-slate-800 tracking-tight">Messages</h2>
        <p class="text-slate-500 mt-1">Chat with customers regarding their orders.</p>
    </div>
</div>

<div class="flex h-[700px] bg-white rounded-[2rem] border border-slate-200 shadow-sm overflow-hidden">
    <!-- Sidebar / Conversation List -->
    <div class="w-1/3 border-r border-slate-200 flex flex-col bg-slate-50/50">
        <div class="p-4 border-b border-slate-200 bg-white">
            <h3 class="font-black text-lg text-slate-800">Conversations</h3>
        </div>
        <div class="flex-1 overflow-y-auto custom-scrollbar">
            @if($conversations->isEmpty())
                <div class="p-8 text-center text-slate-500">
                    <i class="fas fa-inbox text-3xl text-slate-300 mb-3"></i>
                    <p class="font-medium text-sm">No messages yet</p>
                </div>
            @else
                @foreach($conversations as $conv)
                    <a href="{{ route('pharmacy.messages', $conv->id) }}" class="block p-4 border-b border-slate-100 hover:bg-white transition-colors {{ isset($conversation) && $conversation->id == $conv->id ? 'bg-white border-l-4 border-l-blue-500' : '' }}">
                        <div class="flex justify-between items-start mb-1">
                            <h4 class="font-bold text-slate-800 line-clamp-1">{{ $conv->user->name }}</h4>
                            @if($conv->latestMessage)
                                <span class="text-[10px] text-slate-400 whitespace-nowrap ml-2">{{ $conv->latestMessage->created_at->shortAbsoluteDiffForHumans() }}</span>
                            @endif
                        </div>
                        @if($conv->order_id)
                            <span class="inline-block px-2 py-0.5 bg-slate-100 text-slate-500 text-[10px] font-bold rounded mb-1">Order #{{ $conv->order_id }}</span>
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
            <div class="p-4 border-b border-slate-200 flex justify-between items-center bg-white shadow-sm z-10">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center font-bold">
                        {{ substr($conversation->user->name, 0, 1) }}
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800">{{ $conversation->user->name }}</h3>
                        @if($conversation->order)
                            <a href="{{ route('pharmacy.orders', ['status' => 'all']) }}" class="text-xs text-blue-500 hover:underline">View Order #{{ $conversation->order->id }}</a>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Messages List -->
            <div class="flex-1 p-6 overflow-y-auto custom-scrollbar flex flex-col gap-4 bg-slate-50/50" id="chat-messages">
                @foreach($conversation->messages as $msg)
                    @php $isMine = $msg->sender_id === Auth::id(); @endphp
                    <div class="flex {{ $isMine ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-[70%] {{ $isMine ? 'bg-blue-600 text-white rounded-tl-2xl rounded-tr-2xl rounded-bl-2xl' : 'bg-white border border-slate-200 text-slate-800 rounded-tl-2xl rounded-tr-2xl rounded-br-2xl shadow-sm' }} px-4 py-3">
                            <p class="text-sm">{{ $msg->body }}</p>
                            @if($msg->image_path)
                                <img src="{{ asset('storage/' . $msg->image_path) }}" class="mt-2 rounded-lg max-h-48 object-cover">
                            @endif
                            <p class="text-[9px] mt-1 text-right {{ $isMine ? 'text-blue-200' : 'text-slate-400' }}">{{ $msg->created_at->format('h:i A') }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Input Area -->
            <div class="p-4 border-t border-slate-200 bg-white">
                <form action="{{ route('pharmacy.messages.send', $conversation->id) }}" method="POST" enctype="multipart/form-data" class="flex items-center gap-2" id="chat-form">
                    @csrf
                    <label class="cursor-pointer p-3 text-slate-400 hover:text-blue-500 transition-colors rounded-xl hover:bg-slate-50">
                        <i class="fas fa-image text-lg"></i>
                        <input type="file" name="image" class="hidden" accept="image/*">
                    </label>
                    <input type="text" name="message" class="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all" placeholder="Type your message..." required autocomplete="off" autofocus>
                    <button type="submit" class="p-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl shadow-md transition-colors flex items-center justify-center w-12 h-12 shrink-0">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </form>
            </div>
        @else
            <!-- No Conversation Selected -->
            <div class="flex-1 flex flex-col items-center justify-center text-slate-400">
                <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mb-4">
                    <i class="fas fa-comments text-3xl"></i>
                </div>
                <p class="font-medium">Select a conversation to start messaging</p>
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
