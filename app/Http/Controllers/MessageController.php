<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Pharmacy;
use App\Models\User;
use App\Notifications\NewCustomerMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    // =============================================
    // CUSTOMER ROUTES
    // =============================================

    public function index()
    {
        $conversations = Conversation::where('user_id', Auth::id())
            ->with(['pharmacy', 'latestMessage', 'order'])
            ->orderByDesc('last_message_at')
            ->get();

        return view('user.messages', compact('conversations'));
    }

    public function show($id)
    {
        $conversation = Conversation::where('id', $id)
            ->where('user_id', Auth::id())
            ->with(['messages.sender', 'pharmacy', 'order'])
            ->firstOrFail();

        // Mark as read
        $conversation->markAsReadFor(Auth::id());

        if (request()->wantsJson()) {
            return response()->json([
                'messages' => $conversation->messages->map(function ($msg) {
                    return [
                        'id' => $msg->id,
                        'body' => $msg->body,
                        'image_path' => $msg->image_path ? asset('storage/' . $msg->image_path) : null,
                        'is_mine' => $msg->sender_id === Auth::id(),
                        'created_at' => $msg->created_at->format('M d, Y h:i A'),
                        'time_ago' => $msg->created_at->diffForHumans()
                    ];
                })
            ]);
        }

        $conversations = Conversation::where('user_id', Auth::id())
            ->with(['pharmacy', 'latestMessage'])
            ->orderByDesc('last_message_at')
            ->get();

        return view('user.messages', compact('conversations', 'conversation'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'pharmacy_id' => 'required|exists:pharmacies,id',
            'order_id' => 'nullable|exists:orders,id',
            'message' => 'required|string|max:1000'
        ]);

        $pharmacy = Pharmacy::findOrFail($request->pharmacy_id);
        if (!$pharmacy->isEligibleForInquiries()) {
            return back()->withErrors(['pharmacy_id' => 'This pharmacy is currently inactive.']);
        }

        $conversation = Conversation::findOrStart(
            Auth::id(),
            $pharmacy->id,
            $request->order_id
        );

        $message = $conversation->messages()->create([
            'sender_id' => Auth::id(),
            'body' => $request->message,
        ]);

        $conversation->update(['last_message_at' => now()]);

        // Notify pharmacy owner (assuming pharmacy has an owner in Users table)
        $pharmacyUser = \App\Models\User::where('pharmacy_id', $request->pharmacy_id)->first();
        if ($pharmacyUser) {
            $pharmacyUser->notify(new NewCustomerMessage($conversation, Auth::user()->name));
        }

        return redirect()->route('messages.show', $conversation->id);
    }

    // =============================================
    // PHARMACY ROUTES
    // =============================================

    public function pharmacyIndex()
    {
        $pharmacyId = Auth::user()->pharmacy_id;
        if (!$pharmacyId) {
            abort(403, 'Unauthorized pharmacy access.');
        }
        
        $conversations = Conversation::where('pharmacy_id', $pharmacyId)
            ->with(['user', 'latestMessage', 'order'])
            ->orderByDesc('last_message_at')
            ->get();

        if (request()->wantsJson()) {
            return response()->json([
                'conversations' => $conversations->map(function ($conv) {
                    return [
                        'id' => $conv->id,
                        'user_name' => $conv->user->name ?? 'Patient',
                        'latest_message' => $conv->latestMessage->body ?? 'No messages yet',
                        'time_ago' => $conv->latestMessage && $conv->latestMessage->created_at ? $conv->latestMessage->created_at->diffForHumans() : '',
                        'unread_count' => $conv->unreadCountFor(Auth::id()),
                        'url' => route('pharmacy.messages', $conv->id),
                    ];
                })
            ]);
        }

        return view('pharmacy.messages', compact('conversations'));
    }

    public function pharmacyShow($id)
    {
        $pharmacyId = Auth::user()->pharmacy_id;
        if (!$pharmacyId) {
            abort(403, 'Unauthorized pharmacy access.');
        }

        $conversation = Conversation::where('id', $id)
            ->where('pharmacy_id', $pharmacyId)
            ->with(['messages.sender', 'user', 'order'])
            ->firstOrFail();

        // Mark as read
        $conversation->markAsReadFor(Auth::id());

        if (request()->wantsJson()) {
            return response()->json([
                'messages' => $conversation->messages->map(function ($msg) {
                    return [
                        'id' => $msg->id,
                        'body' => $msg->body,
                        'image_path' => $msg->image_path ? asset('storage/' . $msg->image_path) : null,
                        'is_mine' => (int)$msg->sender_id === (int)Auth::id(),
                        'created_at' => $msg->created_at ? $msg->created_at->format('M d, Y h:i A') : '',
                        'time' => $msg->created_at ? $msg->created_at->format('h:i A') : '',
                        'time_ago' => $msg->created_at ? $msg->created_at->diffForHumans() : ''
                    ];
                })
            ]);
        }

        $conversations = Conversation::where('pharmacy_id', $pharmacyId)
            ->with(['user', 'latestMessage'])
            ->orderByDesc('last_message_at')
            ->get();

        return view('pharmacy.messages', compact('conversations', 'conversation'));
    }

    // =============================================
    // SHARED ROUTES
    // =============================================

    public function sendMessage(Request $request, $id)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
            'image' => 'nullable|image|max:2048' // 2MB max
        ]);

        $rawMessage = trim($request->input('message'));
        if ($rawMessage === '') {
            if ($request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'The message field cannot be empty.',
                    'errors' => ['message' => ['The message field cannot be empty.']],
                ], 422);
            }
            return back()->withErrors(['message' => 'The message field cannot be empty.']);
        }

        $conversation = Conversation::findOrFail($id);

        // Ensure user belongs to conversation
        $isCustomer = (int)$conversation->user_id === (int)Auth::id();
        $isPharmacy = Auth::user()->pharmacy_id && ((int)$conversation->pharmacy_id === (int)Auth::user()->pharmacy_id);

        if (!$isCustomer && !$isPharmacy) {
            abort(403, 'Unauthorized conversation access.');
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('chat_images', 'public');
        }

        $message = $conversation->messages()->create([
            'sender_id' => Auth::id(),
            'body' => $rawMessage,
            'image_path' => $imagePath
        ]);

        $conversation->update(['last_message_at' => now()]);

        // Send notification to the other party
        if ($isCustomer) {
            $pharmacyUser = \App\Models\User::where('pharmacy_id', $conversation->pharmacy_id)->first();
            if ($pharmacyUser) {
                try {
                    $pharmacyUser->notify(new NewCustomerMessage($conversation, Auth::user()->name));
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Failed notifying pharmacy: ' . $e->getMessage());
                }
            }
        } else {
            if ($conversation->user) {
                try {
                    $conversation->user->notify(new NewCustomerMessage($conversation, Auth::user()->name ?? 'Pharmacy'));
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Failed notifying customer: ' . $e->getMessage());
                }
            }
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => [
                    'id' => $message->id,
                    'body' => $message->body,
                    'image_path' => $message->image_path ? asset('storage/' . $message->image_path) : null,
                    'is_mine' => true,
                    'created_at' => $message->created_at->format('M d, Y h:i A'),
                    'time_ago' => $message->created_at->diffForHumans()
                ]
            ]);
        }

        return back();
    }

    public function markRead($id)
    {
        $conversation = Conversation::findOrFail($id);
        
        $isCustomer = (int)$conversation->user_id === (int)Auth::id();
        $isPharmacy = Auth::user()->pharmacy_id && ((int)$conversation->pharmacy_id === (int)Auth::user()->pharmacy_id);

        if (!$isCustomer && !$isPharmacy) {
            abort(403, 'Unauthorized conversation access.');
        }

        $conversation->markAsReadFor(Auth::id());

        return response()->json(['success' => true]);
    }

    public function unreadCount()
    {
        $user = Auth::user();
        $count = 0;

        if ($user->pharmacy_id) {
            // Pharmacy unread count
            $conversations = Conversation::where('pharmacy_id', $user->pharmacy_id)->get();
            foreach ($conversations as $conv) {
                $count += $conv->unreadCountFor($user->id);
            }
        } else {
            // Customer unread count
            $conversations = Conversation::where('user_id', $user->id)->get();
            foreach ($conversations as $conv) {
                $count += $conv->unreadCountFor($user->id);
            }
        }

        return response()->json(['count' => $count]);
    }

    /**
     * Mobile API: Start or retrieve conversation for a pharmacy.
     * GET /api/pharmacies/{pharmacy}/conversation
     */
    public function apiGetOrCreateConversation(Request $request, $pharmacyId)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated.'], 401);
        }

        if ($user->role !== User::ROLE_CUSTOMER) {
            return response()->json(['status' => 'error', 'message' => 'Only patient accounts can initiate pharmacy conversations.'], 403);
        }

        $pharmacyId = (int)$pharmacyId;
        if ($pharmacyId <= 0) {
            return response()->json(['status' => 'error', 'message' => 'Invalid pharmacy ID.'], 422);
        }

        $pharmacy = Pharmacy::where('id', $pharmacyId)->first();
        if (!$pharmacy) {
            return response()->json(['status' => 'error', 'message' => 'Pharmacy not found.'], 404);
        }

        // Only approved and active pharmacies can be messaged
        if (!$pharmacy->isEligibleForInquiries()) {
            return response()->json(['status' => 'error', 'message' => 'This pharmacy is currently inactive.'], 422);
        }

        $conversation = Conversation::findOrStart(
            $user->id,
            $pharmacy->id,
            null
        );

        $conversation->markAsReadFor($user->id);

        return response()->json([
            'status' => 'success',
            'conversation_id' => $conversation->id,
            'pharmacy' => [
                'id' => $pharmacy->id,
                'name' => $pharmacy->name,
                'address' => $pharmacy->address,
            ],
            'messages' => $conversation->messages()->with('sender')->get()->map(function ($msg) use ($user) {
                return [
                    'id' => $msg->id,
                    'conversation_id' => $msg->conversation_id,
                    'body' => $msg->body,
                    'image_path' => $msg->image_path ? asset('storage/' . $msg->image_path) : null,
                    'is_mine' => (int)$msg->sender_id === (int)$user->id,
                    'created_at' => $msg->created_at ? $msg->created_at->format('M d, Y h:i A') : '',
                    'time_ago' => $msg->created_at ? $msg->created_at->diffForHumans() : 'Just now',
                ];
            }),
        ]);
    }

    /**
     * Mobile API: Get patient conversations list.
     */
    public function apiConversations(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated.'], 401);
        }

        if ($user->role !== User::ROLE_CUSTOMER) {
            return response()->json(['status' => 'error', 'message' => 'Only patient accounts can access this conversation list.'], 403);
        }

        $conversations = Conversation::where('user_id', $user->id)
            ->with(['pharmacy:id,name,address', 'latestMessage'])
            ->orderByDesc('last_message_at')
            ->get()
            ->map(function ($conv) use ($user) {
                return [
                    'id' => $conv->id,
                    'pharmacy_id' => $conv->pharmacy_id,
                    'pharmacy_name' => $conv->pharmacy->name ?? 'Pharmacy Partner',
                    'last_message' => $conv->latestMessage->body ?? 'No messages yet',
                    'time' => $conv->last_message_at ? $conv->last_message_at->diffForHumans() : 'Recent',
                    'unread_count' => $conv->unreadCountFor($user->id),
                ];
            });

        return response()->json([
            'status' => 'success',
            'conversations' => $conversations,
        ]);
    }

    /**
     * Mobile API: Get messages in a conversation.
     */
    public function apiMessages(Request $request, $id)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated.'], 401);
        }

        if ($user->role !== User::ROLE_CUSTOMER) {
            return response()->json(['status' => 'error', 'message' => 'Only patient accounts can access patient messaging.'], 403);
        }

        $conversation = Conversation::where('id', $id)
            ->where('user_id', $user->id)
            ->with(['messages.sender', 'pharmacy'])
            ->firstOrFail();

        $conversation->markAsReadFor($user->id);

        return response()->json([
            'status' => 'success',
            'conversation_id' => $conversation->id,
            'pharmacy' => [
                'id' => $conversation->pharmacy->id ?? null,
                'name' => $conversation->pharmacy->name ?? 'Pharmacy',
            ],
            'messages' => $conversation->messages->map(function ($msg) use ($user) {
                return [
                    'id' => $msg->id,
                    'conversation_id' => $msg->conversation_id,
                    'body' => $msg->body,
                    'image_path' => $msg->image_path ? asset('storage/' . $msg->image_path) : null,
                    'is_mine' => (int)$msg->sender_id === (int)$user->id,
                    'created_at' => $msg->created_at ? $msg->created_at->format('M d, Y h:i A') : '',
                    'time_ago' => $msg->created_at ? $msg->created_at->diffForHumans() : 'Just now',
                ];
            }),
        ]);
    }

    /**
     * Mobile API: Send a message in a conversation.
     */
    public function apiSend(Request $request, $id)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated.'], 401);
        }

        if ($user->role !== User::ROLE_CUSTOMER) {
            return response()->json(['status' => 'error', 'message' => 'Only patient accounts can send messages via this endpoint.'], 403);
        }

        $rawMessage = $request->input('message') ?? $request->input('body');
        $body = is_string($rawMessage) ? trim($rawMessage) : '';

        if ($body === '') {
            return response()->json([
                'status' => 'error',
                'message' => 'The message field is required.',
                'errors' => ['message' => ['The message field cannot be empty.']],
            ], 422);
        }

        if (mb_strlen($body) > 1000) {
            return response()->json([
                'status' => 'error',
                'message' => 'The message may not be greater than 1000 characters.',
                'errors' => ['message' => ['The message may not be greater than 1000 characters.']],
            ], 422);
        }

        $conversation = Conversation::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $message = $conversation->messages()->create([
            'sender_id' => $user->id,
            'body' => $body,
        ]);

        $conversation->update(['last_message_at' => now()]);

        // Notify recipient (pharmacy user)
        $pharmacyUser = \App\Models\User::where('pharmacy_id', $conversation->pharmacy_id)->first();
        if ($pharmacyUser) {
            try {
                $pharmacyUser->notify(new NewCustomerMessage($conversation, $user->name ?? 'Patient'));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Failed notifying pharmacy: ' . $e->getMessage());
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => [
                'id' => $message->id,
                'conversation_id' => $conversation->id,
                'body' => $message->body,
                'is_mine' => true,
                'created_at' => $message->created_at ? $message->created_at->format('M d, Y h:i A') : '',
                'time_ago' => $message->created_at ? $message->created_at->diffForHumans() : 'Just now',
            ],
        ]);
    }
}
