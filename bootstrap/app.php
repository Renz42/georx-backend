<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Auth;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $configuredProxies = env('TRUSTED_PROXIES');
        $trustedProxyList = ($configuredProxies !== null && trim((string) $configuredProxies) !== '')
            ? array_values(array_filter(array_map('trim', explode(',', (string) $configuredProxies))))
            : [
                '10.0.0.0/8',
                '172.16.0.0/12',
                '192.168.0.0/16',
                '100.64.0.0/10',
                '127.0.0.0/8',
                'fc00::/7',
                'fe80::/10',
                '::1',
            ];

        $middleware->trustProxies(
            at: $trustedProxyList,
            headers: \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR |
                \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST |
                \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT |
                \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO |
                \Illuminate\Http\Request::HEADER_X_FORWARDED_AWS_ELB
        );
        $middleware->redirectGuestsTo(function (\Illuminate\Http\Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return null;
            }

            return \App\Http\Middleware\RoleMiddleware::resolveGuestLoginUrl($request);
        });
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'alijis.geofence' => \App\Http\Middleware\EnsureWithinAlijis::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
        });
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            }
        });
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage() ?: 'An error occurred.',
                ], $e->getStatusCode());
            }
        });
        $exceptions->render(function (\Throwable $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $payload = [
                    'error' => config('app.debug') ? $e->getMessage() : 'Server error.',
                ];
                if (config('app.debug')) {
                    $payload['file'] = $e->getFile();
                    $payload['line'] = $e->getLine();
                }
                return response()->json($payload, 500);
            }
        });
    })->create();

// Note: RoleMiddleware will be created via artisan command:
// php artisan make:middleware RoleMiddleware
