<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'role' => User::ROLE_CUSTOMER, // Safely set to standard customer role string
        ]);

        // Sync user to Supabase Auth
        \App\Services\SupabaseAuthService::createOrSyncUser(
            $request->email,
            $request->password,
            $request->name,
            $request->phone ?? ''
        );

        // Generate Sanctum Bearer Token
        $token = $user->createToken('mobile_app_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'User registered successfully',
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
            'role' => 'nullable|string|in:customer,driver',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        // Validate user exists and password is correct
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid credentials'
            ], 401);
        }

        $requestedRole = $request->input('role', 'customer');

        if ($requestedRole === 'driver') {
            // Check driver role
            if ($user->role !== User::ROLE_DELIVERY_PARTNER && $user->role !== 'driver') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This account is not registered as a GeoRx driver.'
                ], 403);
            }

            // Check account status
            $driverProfile = $user->driverProfile;
            if (!$driverProfile || $driverProfile->account_status !== 'approved') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Driver account is pending admin approval or suspended.'
                ], 403);
            }
        } else {
            // Customer role verification
            if ($user->role === User::ROLE_ADMINISTRATOR || $user->role === User::ROLE_PHARMACY_OWNER || $user->role === User::ROLE_PHARMACIST || $user->role === User::ROLE_PHARMACY_STAFF) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Pharmacy owners and admins should use the Web Portal.'
                ], 403);
            }
        }

        // Generate Sanctum Bearer Token
        $token = $user->createToken('mobile_app_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Logged in successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'driver_profile' => $user->driverProfile,
            ],
            'token' => $token,
        ], 200);
    }

    public function logout(Request $request)
    {
        // Revoke the token that was used to authenticate the current request
        if ($request->user() && $request->user()->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
        }
        
        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully'
        ], 200);
    }

    public function profile(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'status' => 'success',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'driver_profile' => $user->driverProfile,
            ]
        ]);
    }

    public function toggleOnline(Request $request)
    {
        $user = $request->user();
        $profile = $user->driverProfile;

        if (!$profile) {
            return response()->json([
                'status' => 'error',
                'message' => 'Driver profile not found'
            ], 404);
        }

        $profile->is_online = !$profile->is_online;
        $profile->save();

        return response()->json([
            'status' => 'success',
            'is_online' => $profile->is_online,
            'message' => $profile->is_online ? 'You are now ONLINE' : 'You are now OFFLINE'
        ]);
    }
}