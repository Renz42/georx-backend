<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Super Administrator Gate
        Gate::define('manage-system', function ($user) {
            return $user->role === 'administrator';
        });

        // Pharmacy Gate
        Gate::define('manage-pharmacy', function ($user) {
            return $user->role === 'pharmacy_owner' || $user->role === 'administrator';
        });

        // Customer Gate
        Gate::define('access-customer-features', function ($user) {
            return $user->role === 'customer';
        });
    }
}
