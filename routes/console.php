<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// =============================================
// SCHEDULED TASKS
// =============================================
// Run every 15 minutes: auto-expire pending reservations
// that have passed their 24-hour window, and restore stock.
// To activate: add `php artisan schedule:run` to your server's crontab.
Schedule::command('reservations:expire')->everyFifteenMinutes();
