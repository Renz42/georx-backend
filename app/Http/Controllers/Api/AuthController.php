<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmailOtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'phone'    => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Create user with pending_verification status and unverified email
        $user = User::create([
            'name'              => $request->name,
            'email'             => $request->email,
            'phone'             => $request->phone,
            'password'          => Hash::make($request->password),
            'role'              => User::ROLE_CUSTOMER,
            'account_status'    => User::STATUS_PENDING_VERIFICATION,
            'email_verified_at' => null,
        ]);

        // Issue 6-digit OTP challenge via EmailOtpService
        $otpResult = EmailOtpService::createChallenge($user, 'registration');

        return response()->json([
            'status'             => 'verification_required',
            'message'            => 'Account created. Please enter the six-digit verification code sent to your email.',
            'challenge_token'    => $otpResult['challenge_token'],
            'masked_email'       => $otpResult['masked_email'],
            'expires_in_seconds' => $otpResult['expires_in_seconds'] ?? 600,
            'resend_in_seconds'  => $otpResult['resend_in_seconds'] ?? 60,
        ], 202);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|string|email',
            'password' => 'required|string',
            'role'     => 'nullable|string|in:customer,driver',
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
                'status'  => 'error',
                'message' => 'Invalid credentials'
            ], 401);
        }

        $requestedRole = $request->input('role', 'customer');

        if ($requestedRole === 'driver') {
            // Check driver role
            if ($user->role !== User::ROLE_DELIVERY_PARTNER && $user->role !== 'driver') {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'This account is not registered as a GeoRx driver.'
                ], 403);
            }

            // Check account status
            $driverProfile = $user->driverProfile;
            if (!$driverProfile || $driverProfile->account_status !== 'approved') {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Driver account is pending admin approval or suspended.'
                ], 403);
            }
        } else {
            // Customer role verification
            if (in_array($user->role, [
                User::ROLE_ADMINISTRATOR,
                User::ROLE_PHARMACY_OWNER,
                User::ROLE_PHARMACIST,
                User::ROLE_PHARMACY_STAFF
            ])) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Pharmacy owners and admins should use the Web Portal.'
                ], 403);
            }

            // Check if patient email requires verification
            if ($user->account_status === User::STATUS_PENDING_VERIFICATION || $user->email_verified_at === null) {
                $otpResult = EmailOtpService::createChallenge($user, 'email_verification');

                return response()->json([
                    'status'                => 'verification_required',
                    'verification_required' => true,
                    'message'               => 'Please enter the six-digit verification code sent to your email to activate your account.',
                    'challenge_token'       => $otpResult['challenge_token'],
                    'masked_email'          => $otpResult['masked_email'],
                    'expires_in_seconds'    => $otpResult['expires_in_seconds'] ?? 600,
                    'resend_in_seconds'     => $otpResult['resend_in_seconds'] ?? 60,
                ], 202);
            }
        }

        // Generate Sanctum Bearer Token for fully verified accounts
        $token = $user->createToken('mobile_app_token')->plainTextToken;

        return response()->json([
            'status'  => 'success',
            'message' => 'Logged in successfully',
            'user'    => [
                'id'             => $user->id,
                'name'           => $user->name,
                'email'          => $user->email,
                'phone'          => $user->phone,
                'role'           => $user->role,
                'driver_profile' => $user->driverProfile,
            ],
            'token'   => $token,
        ], 200);
    }

    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'challenge_token' => 'required|string',
            'otp'             => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $result = EmailOtpService::verifyChallenge(
            challengeToken: $request->challenge_token,
            submittedOtp: $request->otp
        );

        if (!$result['success']) {
            return response()->json([
                'status'             => 'error',
                'message'            => $result['error'],
                'attempts_remaining' => $result['attempts_remaining'] ?? 0,
            ], $result['status']);
        }

        /** @var User $user */
        $user = $result['user'];

        // Mark user verified and active
        $user->update([
            'email_verified_at' => now(),
            'account_status'    => User::STATUS_ACTIVE,
        ]);

        // Generate Sanctum Bearer Token
        $token = $user->createToken('mobile_app_token')->plainTextToken;

        return response()->json([
            'status'  => 'success',
            'message' => 'Email verified successfully! You are now logged in.',
            'user'    => [
                'id'             => $user->id,
                'name'           => $user->name,
                'email'          => $user->email,
                'phone'          => $user->phone,
                'role'           => $user->role,
                'driver_profile' => $user->driverProfile,
            ],
            'token'   => $token,
        ], 200);
    }

    public function resendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'challenge_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $result = EmailOtpService::resendOtp($request->challenge_token);

        if (!$result['success']) {
            return response()->json([
                'status'            => 'error',
                'message'           => $result['error'],
                'seconds_remaining' => $result['seconds_remaining'] ?? 0,
            ], $result['status']);
        }

        return response()->json([
            'status'             => 'success',
            'message'            => $result['message'],
            'resend_in_seconds'  => $result['resend_in_seconds'],
            'expires_in_seconds' => $result['expires_in_seconds'],
        ], 200);
    }

    public function logout(Request $request)
    {
        // Revoke the token that was used to authenticate the current request
        if ($request->user() && $request->user()->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
        }
        
        return response()->json([
            'status'  => 'success',
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