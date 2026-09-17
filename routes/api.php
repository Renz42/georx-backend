<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\PharmacySearchController;
use App\Http\Controllers\Api\PharmacyCatalogController;
use App\Http\Controllers\Api\MobileOrderController;
use App\Http\Controllers\Api\MobileDriverController;

/*
|--------------------------------------------------------------------------
| API Routes - GEORX
|--------------------------------------------------------------------------
*/

// Public Mobile Auth Routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Public Pharmacy Routes (Consolidated into one group)
Route::prefix('pharmacies')->group(function () {
    Route::get('/', [PharmacyCatalogController::class, 'index']);
    Route::get('/nearby', [PharmacySearchController::class, 'nearby']);
    Route::get('/{id}/catalog', [PharmacyCatalogController::class, 'catalog']);
    Route::get('/{pharmacy}/reviews', [\App\Http\Controllers\OrderController::class, 'getPharmacyReviews']);
});

// Public Geofence Validation Route
Route::post('/geofence/validate', function (Request $request) {
    $request->validate([
        'latitude' => 'required|numeric',
        'longitude' => 'required|numeric',
    ]);

    $isValid = \App\Services\GeofenceService::isInsideAlijis((float)$request->latitude, (float)$request->longitude);

    return response()->json([
        'is_valid' => $isValid,
        'message' => $isValid 
            ? 'Location is within Barangay Alijis service area.' 
            : 'Location is outside the Barangay Alijis boundary radius.',
    ]);
});

// Protected Mobile Routes (Requires Sanctum Bearer Token)
Route::middleware('auth:sanctum')->group(function () {
    // Auth & Profile
    Route::get('/user', [AuthController::class, 'profile']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Notifications
    Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index']);
    Route::post('/notifications/{notification}/read', [\App\Http\Controllers\NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/delete-all', [\App\Http\Controllers\NotificationController::class, 'destroyAll']);
    Route::delete('/notifications/{notification}', [\App\Http\Controllers\NotificationController::class, 'destroy']);

    // Stock Alerts
    Route::post('/stock-alerts', [\App\Http\Controllers\NotificationController::class, 'subscribeStockAlert']);
    Route::delete('/stock-alerts', [\App\Http\Controllers\NotificationController::class, 'unsubscribeStockAlert']);
    Route::get('/stock-alerts', [\App\Http\Controllers\NotificationController::class, 'stockAlerts']);

    // Mobile Push Device Tokens
    Route::post('/device-tokens', [\App\Http\Controllers\DeviceTokenController::class, 'registerToken']);
    Route::delete('/device-tokens', [\App\Http\Controllers\DeviceTokenController::class, 'removeToken']);

    // Patient Orders & Reviews
    Route::get('/user/orders', [MobileOrderController::class, 'index']);
    Route::get('/user/orders/{id}', [MobileOrderController::class, 'show']);
    Route::get('/user/orders/{id}/tracking', [MobileOrderController::class, 'liveTracking']);
    Route::post('/user/orders/{id}/confirm-delivery', [MobileOrderController::class, 'confirmDelivery']);
    Route::post('/orders', [\App\Http\Controllers\OrderController::class, 'placeOrder']);
    Route::post('/orders/{id}/review', [\App\Http\Controllers\OrderController::class, 'submitReview']);

    // Driver Mobile Routes
    Route::prefix('driver')->group(function () {
        Route::post('/toggle-online', [AuthController::class, 'toggleOnline']);
        Route::get('/bookings/available', [MobileDriverController::class, 'availableBookings']);
        Route::post('/bookings/{id}/accept', [MobileDriverController::class, 'acceptBooking']);
        Route::post('/status/update/{id}', [MobileDriverController::class, 'updateStatus']);
        Route::post('/location/update', [MobileDriverController::class, 'updateLocation']);
        Route::get('/deliveries', [MobileDriverController::class, 'history']);
        Route::get('/reviews', [MobileDriverController::class, 'reviews']);
    });
});