<?php

namespace App\Http\Controllers;

use App\Models\UserDeviceToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DeviceTokenController extends Controller
{
    /**
     * Register or update a mobile device push token for the authenticated user.
     */
    public function registerToken(Request $request)
    {
        $validated = $request->validate([
            'device_token' => 'required|string|max:255',
            'device_type' => 'nullable|string|in:android,ios,web',
        ]);

        $user = Auth::user();

        $token = UserDeviceToken::updateOrCreate(
            [
                'user_id' => $user->id,
                'device_token' => $validated['device_token'],
            ],
            [
                'device_type' => $validated['device_type'] ?? 'android',
                'last_used_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Device token registered successfully.',
            'device_token' => $token,
        ]);
    }

    /**
     * Unbind/delete a device push token on logout.
     */
    public function removeToken(Request $request)
    {
        $validated = $request->validate([
            'device_token' => 'required|string|max:255',
        ]);

        $user = Auth::user();

        UserDeviceToken::where('user_id', $user->id)
            ->where('device_token', $validated['device_token'])
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Device token removed.',
        ]);
    }
}
