<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Usage examples:
     *   middleware('role:administrator')
     *   middleware('role:pharmacy_owner,pharmacist,pharmacy_staff')
     *   middleware('role:driver')
     *   middleware('role:driver,delivery_partner')   ← accepts both forms
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user()) {
            return redirect('/login')->with('error', 'Please login to continue.');
        }

        $user = $request->user();

        // =====================================================================
        // ROLE CHECK
        // Treat 'driver' and 'delivery_partner' as the same role for backwards
        // compatibility. Any guard that passes 'driver' will accept a user whose
        // role column is either 'driver' OR 'delivery_partner'.
        // =====================================================================
        $allowedRoles = $this->expandRoles($roles);

        foreach ($allowedRoles as $role) {
            if ($user->role === $role) {
                // *** Additional check for drivers: account must be approved ***
                if (in_array($role, ['driver', 'delivery_partner'])) {
                    return $this->checkDriverApproval($user, $request, $next);
                }
                return $next($request);
            }
        }

        // =====================================================================
        // UNAUTHORIZED — redirect to the correct portal for this user's role
        // =====================================================================
        return $this->redirectToCorrectPortal($user);
    }

    /**
     * If a route requires 'driver', also allow 'delivery_partner' (legacy).
     * If a route requires 'delivery_partner', also allow 'driver' (forward-compat).
     */
    private function expandRoles(array $roles): array
    {
        $expanded = [];
        foreach ($roles as $role) {
            $expanded[] = $role;
            if ($role === 'driver') {
                $expanded[] = 'delivery_partner';
            } elseif ($role === 'delivery_partner') {
                $expanded[] = 'driver';
            }
        }
        return array_unique($expanded);
    }

    /**
     * Driver-specific approval gate.
     * A driver with account_status != 'approved' is blocked even if authenticated.
     */
    private function checkDriverApproval(User $user, Request $request, Closure $next): Response
    {
        $profile = $user->driverProfile;

        if (! $profile || $profile->account_status !== User::STATUS_APPROVED) {
            // Logout so they can't keep hitting the route
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = match ($profile?->account_status) {
                User::STATUS_PENDING_REVIEW => 'Your driver account is pending admin approval. Please check back later.',
                User::STATUS_SUSPENDED      => 'Your driver account has been suspended. Contact support.',
                User::STATUS_REJECTED       => 'Your driver application was not approved.',
                default                     => 'Your driver account is not yet active.',
            };

            return redirect('/driver/login')->with('error', $message);
        }

        return $next($request);
    }

    /**
     * Redirect to the correct portal based on the user's actual role.
     */
    private function redirectToCorrectPortal(User $user): Response
    {
        if ($user->isAdministrator()) {
            return redirect('/admin/dashboard')->with('error', 'Access denied.');
        }

        if ($user->isPharmacyOwner() || $user->isPharmacist() || $user->isPharmacyStaff()) {
            return redirect('/pharmacy/dashboard')->with('error', 'Access denied.');
        }

        if ($user->isDriver()) {
            return redirect('/driver/dashboard')->with('error', 'Access denied.');
        }

        return redirect('/dashboard')->with('error', 'Access denied.');
    }
}
