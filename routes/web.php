<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PharmacySearchController;
use App\Http\Controllers\PharmacyController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\DriverController;

// =============================================
// PUBLIC ROUTES (Anyone can access)
// =============================================
Route::get('/', function () {
    // Guests and customers see the public map
    return view('home');
});

// API for the map to fetch nearby pharmacies
Route::get('/api/pharmacies/nearby', [PharmacySearchController::class, 'nearby']);

// Search API endpoints
Route::get('/api/search/suggestions', [PharmacySearchController::class, 'suggestions']);
Route::get('/api/search/popular', [PharmacySearchController::class, 'popular']);
Route::get('/api/search/recent', [PharmacySearchController::class, 'recent']);

// NEW: Public Storefront Catalog
Route::get('/pharmacy/{id}/catalog', [PharmacyController::class, 'publicCatalog'])->name('public.pharmacy.catalog');

// NEW: Public Pharmacy Profile
Route::get('/pharmacy/{id}/profile', [PharmacyController::class, 'publicProfile'])->name('public.pharmacy.profile');

// NEW: Public Medicine Details
Route::get('/pharmacy/{pharmacy_id}/medicine/{medicine_id}', [PharmacyController::class, 'publicMedicineDetails'])->name('medicine.details');

// =============================================
// AUTHENTICATION & REGISTRATION ROUTES (Guests Only)
// =============================================
    // 1. REGULAR USER (PATIENT) PORTAL
    Route::get('/login', [AuthController::class, 'showUserLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'loginUser']);
    
    // Regular User Registration
    Route::get('/register', [AuthController::class, 'showUserRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'registerUser']);

    // 2. ADMIN PORTAL
    Route::get('/admin/login', [AuthController::class, 'showAdminLogin'])->name('admin.login');
    Route::post('/admin/login', [AuthController::class, 'loginAdmin']);
    
    // 3. PHARMACY PORTAL
    Route::get('/pharmacy/login', [AuthController::class, 'showPharmacyLogin'])->name('pharmacy.login');
    Route::post('/pharmacy/login', [AuthController::class, 'loginPharmacy']);
    
    // Pharmacy Partner Registration
    Route::get('/pharmacy/register', [AuthController::class, 'showPharmacyRegister'])->name('pharmacy.register');
    Route::post('/pharmacy/register', [AuthController::class, 'registerPharmacy']);

    // 4. DRIVER PORTAL
    Route::get('/driver/login', [AuthController::class, 'showDriverLogin'])->name('driver.login');
    Route::post('/driver/login', [AuthController::class, 'loginDriver']);

    // Driver Self-Registration (creates account in pending_review state)
    Route::get('/driver/register', [AuthController::class, 'showDriverRegister'])->name('driver.register');
    Route::post('/driver/register', [AuthController::class, 'registerDriver']);

// Logout Route
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// =============================================
// SUPER ADMIN ROUTES (/admin/*)
// =============================================
Route::prefix('admin')->middleware(['auth:admin', 'role:administrator'])->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('admin.dashboard');
    
    // ✅ NEW: User Management Routes
    Route::get('/users', [AdminController::class, 'usersIndex'])->name('admin.users');
    Route::delete('/users/{id}', [AdminController::class, 'destroyUser'])->name('admin.users.destroy');
    
    // Read-only medicine list
    Route::get('/medicines', [AdminController::class, 'medicines'])->name('admin.medicines');
    
    // Pharmacy management
    Route::get('/pharmacies', [AdminController::class, 'pharmacies'])->name('admin.pharmacies');
    
    // Approval Workflow & Suspension
    Route::get('/pharmacies/{pharmacy}/review', [AdminController::class, 'reviewPharmacy'])->name('admin.pharmacies.review');
    Route::patch('/pharmacies/{pharmacy}/approve', [AdminController::class, 'approvePharmacy'])->name('admin.pharmacies.approve');
    Route::delete('/pharmacies/{pharmacy}/reject', [AdminController::class, 'rejectPharmacy'])->name('admin.pharmacies.reject');
    Route::patch('/pharmacies/{pharmacy}/suspend', [AdminController::class, 'suspendPharmacy'])->name('admin.pharmacies.suspend');
    
    // Editing and Inventory Auditing
    Route::get('/pharmacies/{pharmacy}/edit', [AdminController::class, 'editPharmacy'])->name('admin.pharmacies.edit');
    Route::put('/pharmacies/{pharmacy}', [AdminController::class, 'updatePharmacy'])->name('admin.pharmacies.update');
    Route::delete('/pharmacies/{pharmacy}', [AdminController::class, 'deletePharmacy'])->name('admin.pharmacies.destroy');
    Route::get('/pharmacies/{pharmacy}/inventory', [AdminController::class, 'pharmacyInventory'])->name('admin.pharmacies.inventory');
    
    // Lightweight Reports
    Route::get('/reports', [AdminController::class, 'reports'])->name('admin.reports');
    
    // Platform Settings
    Route::get('/settings', [AdminController::class, 'settings'])->name('admin.settings');
    Route::post('/settings', [AdminController::class, 'updateSettings'])->name('admin.settings.update');

    // ── Driver Management (Super Admin) ──────────────────────────────────────
    Route::get('/drivers', [AdminController::class, 'driversIndex'])->name('admin.drivers');
    Route::patch('/drivers/{driver}/approve', [AdminController::class, 'approveDriver'])->name('admin.drivers.approve');
    Route::patch('/drivers/{driver}/suspend', [AdminController::class, 'suspendDriver'])->name('admin.drivers.suspend');
    Route::patch('/drivers/{driver}/reject',  [AdminController::class, 'rejectDriver'])->name('admin.drivers.reject');
});

// =============================================
// PHARMACY ADMIN ROUTES (/portal/* & /pharmacy/*)
// =============================================
$pharmacyPortalRoutes = function () {
    Route::get('/dashboard', [PortalController::class, 'dashboard'])->name('dashboard');
    Route::get('/dashboard/report', [PortalController::class, 'downloadReport'])->name('report');
    
    // Audit Logs
    Route::get('/audit-logs', [PortalController::class, 'auditLogs'])->name('audit_logs');
    
    // Inventory (add stocks, price, expiration)
    Route::get('/inventory', [PortalController::class, 'inventory'])->name('inventory');
    
    // Creates new global medicine + adds to stock
    Route::post('/inventory/new-medicine', [PortalController::class, 'storeNewMedicine'])->name('inventory.storeNew');
    
    Route::post('/inventory', [PortalController::class, 'addToInventory'])->name('inventory.add');
    Route::put('/inventory/{medicine}', [PortalController::class, 'updateInventoryItem'])->name('inventory.update');
    Route::delete('/inventory/{medicine}', [PortalController::class, 'removeFromInventory'])->name('inventory.remove');
    
    // NEW: Pharmacy Admin Catalog Management
    Route::get('/catalog', [PortalController::class, 'catalog'])->name('catalog');
    Route::post('/catalog/{id}', [PortalController::class, 'updateCatalog'])->name('catalog.update');
    
    // Profile (read-only view)
    Route::get('/profile', [PortalController::class, 'profile'])->name('profile');
    Route::put('/profile', [PortalController::class, 'updateProfile'])->name('profile.update');

    // Settings (Theme Color, Visibility, etc.)
    Route::get('/settings', [PortalController::class, 'settings'])->name('settings');
    Route::post('/settings', [PortalController::class, 'updateSettings'])->name('settings.update');

    // Incoming Orders
    Route::get('/orders', [PortalController::class, 'orders'])->name('orders');
    Route::post('/orders/{id}/confirm', [PortalController::class, 'confirmOrder'])->name('orders.confirm');
    Route::post('/orders/{id}/reject', [PortalController::class, 'rejectOrder'])->name('orders.reject');
    Route::post('/orders/{id}/prepare', [PortalController::class, 'prepareOrder'])->name('orders.prepare');

    // Inventory Batch Management & Analytics
    Route::get('/inventory/{medicine}/batches', [PortalController::class, 'batchDetails'])->name('inventory.batches');
    Route::post('/inventory/batches', [PortalController::class, 'addBatch'])->name('inventory.batches.add');
    Route::patch('/inventory/batches/{batch}/toggle', [PortalController::class, 'toggleBatchStatus'])->name('inventory.batches.toggle');
    Route::get('/analytics/summary', [PortalController::class, 'operationalSummary'])->name('analytics.summary');

    // Pharmacy Messaging
    Route::get('/messages', [\App\Http\Controllers\MessageController::class, 'pharmacyIndex'])->name('messages.index');
    Route::get('/messages/{id}', [\App\Http\Controllers\MessageController::class, 'pharmacyShow'])->name('messages');
    Route::post('/messages/{id}/send', [\App\Http\Controllers\MessageController::class, 'sendMessage'])->name('messages.send');
    Route::post('/messages/{id}/read', [\App\Http\Controllers\MessageController::class, 'markRead'])->name('messages.read');
};

Route::prefix('portal')->name('portal.')->middleware(['auth:pharmacy', 'role:pharmacy_owner,pharmacist,pharmacy_staff'])->group($pharmacyPortalRoutes);
Route::prefix('pharmacy')->name('pharmacy.')->middleware(['auth:pharmacy', 'role:pharmacy_owner,pharmacist,pharmacy_staff'])->group($pharmacyPortalRoutes);

// =============================================
// REGULAR USER ROUTES (/dashboard, favorites, etc.)
// =============================================
Route::middleware(['auth:web', 'role:customer'])->group(function () {
    // User dashboard
    Route::get('/dashboard', [UserController::class, 'dashboard'])->name('user.dashboard');

    // Notifications
    Route::get('/notifications', [UserController::class, 'notifications'])->name('user.notifications');
    Route::get('/ajax/notifications', [NotificationController::class, 'dropdown'])->name('api.notifications');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    // Stock Alerts
    Route::get('/my-restock-alerts', [NotificationController::class, 'indexRestockAlerts'])->name('user.restock-alerts');
    Route::post('/stock-alerts', [NotificationController::class, 'subscribeStockAlert'])->name('stock-alerts.subscribe');
    Route::delete('/stock-alerts', [NotificationController::class, 'unsubscribeStockAlert'])->name('stock-alerts.unsubscribe');
    Route::get('/ajax/stock-alerts', [NotificationController::class, 'stockAlerts'])->name('api.stock-alerts');

    // Favorites
    Route::get('/favorites', [UserController::class, 'favorites'])->name('user.favorites');
    Route::post('/favorites/toggle', [FavoriteController::class, 'toggle'])->name('favorites.toggle');
    Route::post('/favorites/check', [FavoriteController::class, 'check'])->name('favorites.check');
    Route::post('/favorites/check-multiple', [FavoriteController::class, 'checkMultiple'])->name('favorites.check-multiple');
    Route::delete('/favorites', [FavoriteController::class, 'remove'])->name('favorites.remove');

    // Cart System
    Route::get('/cart', [\App\Http\Controllers\CartController::class, 'index'])->name('cart.index');
    Route::post('/cart/add', [\App\Http\Controllers\CartController::class, 'add'])->name('cart.add');
    Route::delete('/cart/{id}', [\App\Http\Controllers\CartController::class, 'remove'])->name('cart.remove');
    Route::post('/cart/clear', [\App\Http\Controllers\CartController::class, 'clear'])->name('cart.clear');

    // Orders & Checkout
    Route::get('/my-orders', [\App\Http\Controllers\OrderController::class, 'myOrders'])->name('user.orders');
    Route::get('/checkout', [\App\Http\Controllers\OrderController::class, 'checkout'])->name('orders.checkout');
    Route::post('/checkout', [\App\Http\Controllers\OrderController::class, 'placeOrder'])->name('orders.place');
    Route::get('/order/{id}', [\App\Http\Controllers\OrderController::class, 'show'])->name('orders.show');
    Route::get('/order/{id}/live-location', [\App\Http\Controllers\OrderController::class, 'liveLocation'])->name('orders.live-location');
    Route::post('/order/{id}/cancel', [\App\Http\Controllers\OrderController::class, 'cancelOrder'])->name('orders.cancel');
    Route::get('/order/{id}/receipt', [\App\Http\Controllers\OrderController::class, 'downloadReceipt'])->name('orders.receipt');
    Route::post('/order/{id}/review', [\App\Http\Controllers\OrderController::class, 'submitReview'])->name('orders.review');

    // Messaging
    Route::get('/messages', [\App\Http\Controllers\MessageController::class, 'index'])->name('messages.index');
    Route::get('/messages/{id}', [\App\Http\Controllers\MessageController::class, 'show'])->name('messages.show');
    Route::post('/messages', [\App\Http\Controllers\MessageController::class, 'store'])->name('messages.store');
    Route::post('/messages/{id}/send', [\App\Http\Controllers\MessageController::class, 'sendMessage'])->name('messages.send');
    Route::post('/messages/{id}/read', [\App\Http\Controllers\MessageController::class, 'markRead'])->name('messages.read');
    Route::get('/ajax/messages/unread', [\App\Http\Controllers\MessageController::class, 'unreadCount'])->name('api.messages.unread');
});

// =============================================
// DELIVERY PARTNER ROUTES (/delivery/*)
// =============================================
Route::prefix('delivery')->middleware(['auth', 'role:delivery_partner'])->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\DeliveryController::class, 'dashboard'])->name('delivery.dashboard');
    Route::post('/orders/{id}/accept', [\App\Http\Controllers\DeliveryController::class, 'acceptOrder'])->name('delivery.accept');
    Route::post('/orders/{id}/status', [\App\Http\Controllers\DeliveryController::class, 'updateStatus'])->name('delivery.updateStatus');
});

// =============================================
// DRIVER PORTAL ROUTES (/driver/*)
// =============================================
// All /driver/* routes require:
//   1. auth:driver guard (session must exist)
//   2. role:driver middleware (role check + approval check)
// A driver CANNOT access /admin/*, /pharmacy/*, or /dashboard (customer).
Route::prefix('driver')
    ->name('driver.')
    ->middleware(['auth:driver', 'role:driver'])
    ->group(function () {

    // Dashboard — active deliveries, stats
    Route::get('/dashboard', [DriverController::class, 'dashboard'])->name('dashboard');

    // Available bookings feed
    Route::get('/bookings', [DriverController::class, 'bookings'])->name('bookings');

    // Single booking detail — only shows bookings this driver owns or unassigned ones
    Route::get('/bookings/{id}', [DriverController::class, 'showBooking'])->name('bookings.show');

    // Interactive Delivery Map — pickup & dropoff coordinates
    Route::get('/map/{id}', [DriverController::class, 'deliveryMap'])->name('map');

    // Accept a booking
    Route::post('/bookings/{id}/accept', [DriverController::class, 'acceptBooking'])->name('bookings.accept');

    // Update delivery status (at_pharmacy, picked_up, delivered)
    Route::post('/bookings/{id}/status', [DriverController::class, 'updateStatus'])->name('bookings.status');

    // Toggle online/offline
    Route::post('/availability', [DriverController::class, 'toggleAvailability'])->name('availability');

    // Live GPS Location update (Phase 9)
    Route::post('/location/update', [DriverController::class, 'updateLocation'])->name('location.update');
});

// Delivery Webhook API (No Auth needed)
Route::post('/api/delivery/webhook/{order}', [\App\Http\Controllers\DeliveryController::class, 'webhook'])->name('api.delivery.webhook')->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
