<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function dashboard()
    {
        $user = Auth::user();
        
        $notifications = $user->notifications()->limit(10)->get();
        $unreadCount = $user->unreadNotifications()->count();
        $favorites = $user->favoritePharmacies()->get();
        
        return view('user.dashboard', compact(
            'user', 
            'notifications', 
            'unreadCount',
            'favorites'
        ));
    }

    public function notifications()
    {
        $user = Auth::user();
        
        // FIX: Removed the ->with(['pharmacy', 'medicine']) to prevent RelationNotFoundException. 
        // Notifications usually just contain message text/data, not direct database links to medicines.
        $notifications = $user->notifications()
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        
        return view('user.notifications', compact('user', 'notifications'));
    }

    public function favorites()
    {
        $user = Auth::user();
        
        $favorites = $user->favoritePharmacies()
            ->with('medicines')
            ->get();
        
        return view('user.favorites', compact('user', 'favorites'));
    }
}