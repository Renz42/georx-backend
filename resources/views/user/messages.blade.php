<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Messages - GEORX: A Medicine Hub Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Outfit', 'sans-serif'] },
                    colors: {
                        geo: { 900: '#0F172A', 800: '#1E293B', 700: '#1F2E2C', 600: '#2F7E6A', 500: '#63C6A7', 400: '#BFE8D6', 300: '#A0D8C4', 100: '#E9F7F2', 50: '#F8FAFC' }
                    }
                }
            }
        }
    </script><style>.custom-scrollbar::-webkit-scrollbar { width: 4px; height: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 pb-20 lg:pb-0 h-screen flex flex-col overflow-hidden">

    <x-navbar />

    <main class="flex-1 max-w-5xl mx-auto w-full p-4 lg:p-8 flex flex-col min-h-0">
        <div class="flex-1 flex bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden relative">
            
            <!-- Conversations List -->
            <div class="w-full sm:w-1/3 border-r border-slate-200 flex flex-col bg-slate-50/50 {{ isset($conversation) ? 'hidden sm:flex' : 'flex' }}">
                <div class="p-4 border-b border-slate-200 bg-white">
                    <h3 class="font-black text-lg text-slate-800">Chats</h3>
                </div>
                <div class="flex-1 overflow-y-auto custom-scrollbar">
                    @if($conversations->isEmpty())
                        <div class="p-8 text-center text-slate-500">
                            <i class="fas fa-comments text-3xl text-slate-300 mb-3"></i>
                            <p class="font-medium text-sm">No messages yet</p>
                        </div>
                    @else
                        @foreach($conversations as $conv)
                            <a href="{{ route('messages.show', $conv->id) }}" class="block p-4 border-b border-slate-100 hover:bg-white transition-colors {{ isset($conversation) && $conversation->id == $conv->id ? 'bg-white border-l-4 border-l-blue-500' : '' }}">
                                <div class="flex justify-between items-start mb-1">
                                    <h4 class="font-bold text-slate-800 line-clamp-1">{{ $conv->pharmacy->name }}</h4>
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
            <div class="w-full sm:w-2/3 flex flex-col bg-white {{ isset($conversation) ? 'flex' : 'hidden sm:flex' }}">
                @if(isset($conversation))
                    <!-- Header -->
                    <div class="p-4 border-b border-slate-200 flex justify-between items-center bg-white shadow-sm z-10 shrink-0">
                        <div class="flex items-center gap-3">
                            <a href="{{ route('messages.index') }}" class="sm:hidden text-slate-400 hover:text-slate-800 p-2 -ml-2">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                            <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center font-bold overflow-hidden">
                                @if($conversation->pharmacy->logo)
                                    <img src="{{ asset('storage/' . $conversation->pharmacy->logo) }}" class="w-full h-full object-cover">
                                @else
                                    <i class="fas fa-store"></i>
                                @endif
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-800 leading-tight">{{ $conversation->pharmacy->name }}</h3>
                                @if($conversation->order)
                                    <a href="{{ route('orders.show', $conversation->order->id) }}" class="text-[10px] font-bold text-blue-500 hover:underline uppercase tracking-wider">View Order #{{ $conversation->order->id }}</a>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Messages List -->
                    <div class="flex-1 p-4 sm:p-6 overflow-y-auto custom-scrollbar flex flex-col gap-4 bg-slate-50/50 min-h-0" id="chat-messages">
                        @foreach($conversation->messages as $msg)
                            @php $isMine = $msg->sender_id === Auth::id(); @endphp
                            <div class="flex {{ $isMine ? 'justify-end' : 'justify-start' }}">
                                <div class="max-w-[80%] sm:max-w-[70%] {{ $isMine ? 'bg-blue-600 text-white rounded-tl-2xl rounded-tr-2xl rounded-bl-2xl' : 'bg-white border border-slate-200 text-slate-800 rounded-tl-2xl rounded-tr-2xl rounded-br-2xl shadow-sm' }} px-4 py-3">
                                    <p class="text-sm">{{ $msg->body }}</p>
                                    @if($msg->image_path)
                                        <img src="{{ asset('storage/' . $msg->image_path) }}" class="mt-2 rounded-lg max-h-48 object-cover">
                                    @endif
                                    <p class="text-[9px] mt-1 {{ $isMine ? 'text-blue-200 text-right' : 'text-slate-400' }}">{{ $msg->created_at->format('h:i A') }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Input Area -->
                    <div class="p-3 sm:p-4 border-t border-slate-200 bg-white shrink-0 pb-safe">
                        <form action="{{ route('messages.send', $conversation->id) }}" method="POST" enctype="multipart/form-data" class="flex items-center gap-2" id="chat-form">
                            @csrf
                            <label class="cursor-pointer p-2 sm:p-3 text-slate-400 hover:text-blue-500 transition-colors rounded-xl hover:bg-slate-50">
                                <i class="fas fa-image text-lg"></i>
                                <input type="file" name="image" class="hidden" accept="image/*">
                            </label>
                            <input type="text" name="message" class="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 sm:py-3 text-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all" placeholder="Type a message..." required autocomplete="off" autofocus>
                            <button type="submit" class="w-10 h-10 sm:w-12 sm:h-12 bg-blue-600 hover:bg-blue-700 text-white rounded-xl shadow-md transition-colors flex items-center justify-center shrink-0">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </form>
                    </div>
                @else
                    <!-- No Conversation Selected (Desktop only) -->
                    <div class="flex-1 flex flex-col items-center justify-center text-slate-400">
                        <div class="w-24 h-24 bg-slate-50 rounded-full flex items-center justify-center mb-4">
                            <i class="fas fa-paper-plane text-4xl"></i>
                        </div>
                        <h3 class="font-black text-xl text-slate-700 mb-1">Your Messages</h3>
                        <p class="text-sm">Select a conversation to start chatting</p>
                    </div>
                @endif
            </div>
        </div>
    </main>

    @if(isset($conversation))
    <script>
        const chatMessages = document.getElementById('chat-messages');
        chatMessages.scrollTop = chatMessages.scrollHeight;
    </script>
    @endif
</body>
</html>
