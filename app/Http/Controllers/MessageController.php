<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
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

        $conversation = Conversation::findOrStart(
            Auth::id(),
            $request->pharmacy_id,
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
        
        $conversations = Conversation::where('pharmacy_id', $pharmacyId)
            ->with(['user', 'latestMessage', 'order'])
            ->orderByDesc('last_message_at')
            ->get();

        return view('pharmacy.messages', compact('conversations'));
    }

    public function pharmacyShow($id)
    {
        $pharmacyId = Auth::user()->pharmacy_id;

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
                        'is_mine' => $msg->sender_id === Auth::id(),
                        'created_at' => $msg->created_at->format('M d, Y h:i A'),
                        'time_ago' => $msg->created_at->diffForHumans()
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

        $conversation = Conversation::findOrFail($id);

        // Ensure user belongs to conversation
        $isCustomer = $conversation->user_id === Auth::id();
        $isPharmacy = $conversation->pharmacy_id === Auth::user()->pharmacy_id;

        if (!$isCustomer && !$isPharmacy) {
            abort(403);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('chat_images', 'public');
        }

        $message = $conversation->messages()->create([
            'sender_id' => Auth::id(),
            'body' => $request->message,
            'image_path' => $imagePath
        ]);

        $conversation->update(['last_message_at' => now()]);

        // Send notification to the other party
        if ($isCustomer) {
            $pharmacyUser = \App\Models\User::where('pharmacy_id', $conversation->pharmacy_id)->first();
            if ($pharmacyUser) {
                $pharmacyUser->notify(new NewCustomerMessage($conversation, Auth::user()->name));
            }
        } else {
            $conversation->user->notify(new NewCustomerMessage($conversation, Auth::user()->name ?? 'Pharmacy'));
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
        
        $isCustomer = $conversation->user_id === Auth::id();
        $isPharmacy = $conversation->pharmacy_id === Auth::user()->pharmacy_id;

        if (!$isCustomer && !$isPharmacy) {
            abort(403);
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
}
