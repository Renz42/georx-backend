<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\GeofenceService;
use Symfony\Component\HttpFoundation\Response;

class EnsureWithinAlijis
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $lat = $request->input('latitude');
        $lng = $request->input('longitude');

        if ($lat !== null && $lng !== null && is_numeric($lat) && is_numeric($lng)) {
            if (!GeofenceService::isInsideAlijis((float)$lat, (float)$lng)) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'The specified coordinates are outside the Barangay Alijis service area boundary.',
                    ], 422);
                }

                return back()
                    ->withInput()
                    ->withErrors(['location' => 'The specified coordinates are outside the Barangay Alijis service area boundary.']);
            }
        }

        return $next($request);
    }
}
