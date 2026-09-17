<?php

namespace App\Http\Controllers;

use App\Models\DriverProfile;
use App\Models\Medicine;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    // =============================================
    // DASHBOARD
    // =============================================

    public function dashboard()
    {
        $cachedDashboard = \Illuminate\Support\Facades\Cache::remember('admin_dashboard_data', 60, function () {
            // Core entity counts
            $stats = [
                'pharmacies' => Pharmacy::where('status', '!=', 'rejected')->count(), 
                'pending_pharmacies' => Pharmacy::where('status', 'pending')->count(),
                'active_pharmacies' => Pharmacy::where('status', 'approved')->where('is_active', true)->count(),
                'medicines' => Medicine::count(),
                'users' => User::count(),
            ];

            // Order & Revenue stats
            $totalOrders = \App\Models\Order::count();
            $deliveredOrders = \App\Models\Order::where('status', 'delivered')->count();
            $pendingOrders = \App\Models\Order::whereIn('status', ['pending_confirmation', 'pending', 'accepted', 'at_pharmacy', 'picked_up'])->count();
            $platformRevenue = \App\Models\Order::where('status', 'delivered')->sum('total_amount');

            // User role breakdown for pie chart
            $roleCounts = [
                'Customers'        => User::where('role', 'customer')->count(),
                'Pharmacy Owners'  => User::where('role', 'pharmacy_owner')->count(),
                'Pharmacists'      => User::where('role', 'pharmacist')->count(),
                'Pharmacy Staff'   => User::where('role', 'pharmacy_staff')->count(),
                'Drivers'          => User::whereIn('role', ['driver', 'delivery_partner'])->count(),
                'Administrators'   => User::where('role', 'administrator')->count(),
            ];

            // Monthly platform growth (last 6 months)
            $monthlyGrowth = [];
            for ($i = 5; $i >= 0; $i--) {
                $month = now()->subMonths($i);
                $monthlyGrowth[] = [
                    'month' => $month->format('M Y'),
                    'users' => User::whereYear('created_at', $month->year)->whereMonth('created_at', $month->month)->count(),
                    'orders' => \App\Models\Order::whereYear('created_at', $month->year)->whereMonth('created_at', $month->month)->count(),
                ];
            }

            // Phase 3: Most Searched Medicines
            $mostSearched = \App\Models\SearchLog::select('term')
                ->selectRaw('COUNT(*) as search_count')
                ->groupBy('term')
                ->orderByDesc('search_count')
                ->limit(8)
                ->get();

            // Phase 3: Most Ordered Medicines
            $mostOrdered = \App\Models\OrderItem::select('medicine_id')
                ->selectRaw('SUM(quantity) as total_qty, COUNT(*) as order_count')
                ->groupBy('medicine_id')
                ->orderByDesc('total_qty')
                ->limit(8)
                ->with('medicine:id,brand_name,generic_name')
                ->get();

            // Inventory Status
            $lowStockCount = \DB::table('pharmacy_medicine')
                ->where('quantity_on_hand', '>', 0)
                ->where('quantity_on_hand', '<=', \DB::raw('reorder_level'))
                ->count();
            
            $outOfStockCount = \DB::table('pharmacy_medicine')
                ->where('quantity_on_hand', '<=', 0)
                ->count();

            return compact(
                'stats', 'totalOrders', 'deliveredOrders', 'pendingOrders',
                'platformRevenue', 'roleCounts', 'monthlyGrowth',
                'mostSearched', 'mostOrdered', 'lowStockCount', 'outOfStockCount'
            );
        });

        // Recent orders live (not cached for freshness)
        $recentOrders = \App\Models\Order::with('user', 'pharmacy')
            ->latest()
            ->take(5)
            ->get();

        return view('admin.dashboard', array_merge($cachedDashboard, ['recentOrders' => $recentOrders]));
    }

    // =============================================
    // LIGHTWEIGHT ADMIN REPORTS
    // =============================================

    public function reports()
    {
        // Summary counts
        $totalUsers       = User::count();
        $totalPharmacies  = Pharmacy::where('status', 'approved')->count();
        $totalMedicines   = Medicine::count();
        $pendingOrders    = \App\Models\Order::whereIn('status', [
            'pending_confirmation', 'pending', 'accepted', 'at_pharmacy', 'picked_up'
        ])->count();
        $completedOrders  = \App\Models\Order::where('status', 'delivered')->count();

        // Most Ordered Medicines (top 15)
        $mostOrdered = \App\Models\OrderItem::select('medicine_id')
            ->selectRaw('SUM(quantity) as total_qty, COUNT(DISTINCT order_id) as order_count')
            ->groupBy('medicine_id')
            ->orderByDesc('total_qty')
            ->limit(15)
            ->with('medicine:id,brand_name,generic_name,drug_category')
            ->get();

        // Low Stock Medicines across all pharmacies (qty > 0 but <= 10)
        $lowStockMedicines = \DB::table('pharmacy_medicine')
            ->join('medicines', 'pharmacy_medicine.medicine_id', '=', 'medicines.id')
            ->join('pharmacies', 'pharmacy_medicine.pharmacy_id', '=', 'pharmacies.id')
            ->where('pharmacy_medicine.quantity_on_hand', '>', 0)
            ->where('pharmacy_medicine.quantity_on_hand', '<=', 10)
            ->select(
                'medicines.brand_name',
                'medicines.generic_name',
                'pharmacies.name as pharmacy_name',
                'pharmacy_medicine.quantity_on_hand',
                'pharmacy_medicine.selling_price'
            )
            ->orderBy('pharmacy_medicine.quantity_on_hand')
            ->limit(30)
            ->get();

        // Out of Stock count
        $outOfStockCount = \DB::table('pharmacy_medicine')
            ->where('quantity_on_hand', '<=', 0)
            ->count();

        return view('admin.reports', compact(
            'totalUsers', 'totalPharmacies', 'totalMedicines',
            'pendingOrders', 'completedOrders',
            'mostOrdered', 'lowStockMedicines', 'outOfStockCount'
        ));
    }

    // =============================================
    // PHARMACY APPROVAL WORKFLOW
    // =============================================

    public function pharmacies()
    {
        $pendingPharmacies = Pharmacy::where('status', 'pending')->latest()->get();
        $activePharmacies = Pharmacy::where('status', 'approved')->latest()->get();
        $rejectedPharmacies = Pharmacy::whereIn('status', ['rejected', 'suspended'])->latest()->get();
            
        return view('admin.pharmacies', compact('pendingPharmacies', 'activePharmacies', 'rejectedPharmacies'));
    }

    public function reviewPharmacy(Pharmacy $pharmacy)
    {
        return view('admin.pharmacy_review', compact('pharmacy'));
    }

    public function approvePharmacy(Pharmacy $pharmacy)
    {
        if (!\App\Services\GeofenceService::isInsideAlijis((float)$pharmacy->latitude, (float)$pharmacy->longitude)) {
            return back()->with('error', "Cannot approve {$pharmacy->name} because its coordinates are outside the Barangay Alijis boundary.");
        }

        $pharmacy->update([
            'is_approved' => true,
            'is_active' => true, 
            'status' => 'approved' 
        ]);

        return redirect()->route('admin.pharmacies')->with('success', "{$pharmacy->name} has been approved and is now live!");
    }

    public function rejectPharmacy(Pharmacy $pharmacy)
    {
        // 1. Delete the user account first to free up the email!
        User::where('pharmacy_id', $pharmacy->id)->delete();
        
        // 2. Completely delete the pharmacy from the database
        $pharmacy->delete();

        return redirect()->route('admin.pharmacies')->with('success', 'Pharmacy application has been totally deleted and the email is free.');
    }

    public function suspendPharmacy(Pharmacy $pharmacy)
    {
        // Turn them off but keep their data
        $pharmacy->update([
            'is_active' => false,
            'status' => 'suspended'
        ]);

        return redirect()->route('admin.pharmacies')->with('success', "{$pharmacy->name} has been safely suspended from the public map.");
    }

    // =============================================
    // PHARMACY MANAGEMENT (Editing existing ones)
    // =============================================

    public function editPharmacy(Pharmacy $pharmacy)
    {
        return view('admin.edit', compact('pharmacy'));
    }

    public function updatePharmacy(Request $request, Pharmacy $pharmacy)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'owner_name' => 'nullable|string|max:255',
            'license_number' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'operating_hours_open' => 'nullable|string',
            'operating_hours_close' => 'nullable|string',
            'cover_photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        if (!\App\Services\GeofenceService::isInsideAlijis((float)$validated['latitude'], (float)$validated['longitude'])) {
            return back()->withErrors([
                'latitude' => 'The coordinates provided are outside the boundary of Barangay Alijis. A pharmacy must remain within Barangay Alijis boundaries.'
            ])->withInput();
        }

        $data = [
            'name' => $validated['name'],
            'address' => $validated['address'],
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?? null,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'owner_name' => $validated['owner_name'] ?? null,
            'license_number' => $validated['license_number'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'operating_hours' => [
                'open' => $validated['operating_hours_open'] ?? '8:00 AM',
                'close' => $validated['operating_hours_close'] ?? '9:00 PM',
            ],
        ];

        if ($request->hasFile('cover_photo')) {
            $storageService = app(\App\Services\SupabaseStorageService::class);
            if ($pharmacy->cover_photo) {
                $storageService->delete(\App\Services\SupabaseStorageService::BUCKET_PHARMACY_COVERS, $pharmacy->cover_photo);
            }
            $data['cover_photo'] = $storageService->upload($request->file('cover_photo'), \App\Services\SupabaseStorageService::BUCKET_PHARMACY_COVERS);
        }

        $pharmacy->update($data);

        return redirect()->route('admin.pharmacies')->with('success', 'Pharmacy updated successfully!');
    }

    public function deletePharmacy(Pharmacy $pharmacy)
    {
        // 1. Delete user account to free email
        User::where('pharmacy_id', $pharmacy->id)->delete(); 
        
        // 2. Detach medicines and delete pharmacy
        $pharmacy->medicines()->detach();
        $pharmacy->delete(); 
        
        return redirect()->route('admin.pharmacies')->with('success', 'Pharmacy completely deleted and email freed up!');
    }

    // =============================================
    // READ-ONLY VIEWS FOR SUPER ADMIN
    // =============================================

    // Super Admin can view a specific pharmacy's inventory, but not manage it
    public function pharmacyInventory(Pharmacy $pharmacy)
    {
        $inventory = $pharmacy->medicines;
        return view('admin.inventory', compact('pharmacy', 'inventory'));
    }

    // Super Admin can view the global list of all medicines added by partners
    public function medicines()
    {
        $medicines = Medicine::orderBy('generic_name')->get();
        return view('admin.medicines', compact('medicines'));
    }

    // =============================================
    // USER MANAGEMENT
    // =============================================

    public function usersIndex()
    {
        // Keep stats consistent with the dashboard for the top cards
        $stats = [
            'pharmacies' => Pharmacy::where('status', '!=', 'rejected')->count(), 
            'active_pharmacies' => Pharmacy::where('status', 'approved')->where('is_active', true)->count(),
            'users' => User::count(),
        ];

        // Fetch all users to display in the table
        $usersList = User::orderBy('created_at', 'desc')->get();

        return view('admin.users', compact('stats', 'usersList'));
    }

    public function destroyUser($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return redirect()->route('admin.users')->with('success', 'User profile deleted successfully from the system.');
    }

    // =============================================
    // PLATFORM SETTINGS
    // =============================================
    public function settings()
    {
        $settings = \App\Models\GlobalSetting::getSettings();
        return view('admin.settings', compact('settings'));
    }

    public function updateSettings(Request $request)
    {
        $request->validate([
            'base_delivery_fee'  => 'required|numeric|min:0',
        ]);

        \App\Models\GlobalSetting::set('enable_new_registrations', $request->has('enable_new_registrations') ? '1' : '0');
        \App\Models\GlobalSetting::set('base_delivery_fee',        $request->base_delivery_fee);

        return back()->with('success', 'Platform settings updated successfully.');
    }

    // =============================================
    // DRIVER MANAGEMENT
    // =============================================

    /**
     * List all drivers (pending, approved, suspended, rejected).
     * Super Admin only.
     */
    public function driversIndex()
    {
        $pendingDrivers   = DriverProfile::with('user')
            ->where('account_status', DriverProfile::STATUS_PENDING_REVIEW)
            ->latest()
            ->get();

        $approvedDrivers  = DriverProfile::with('user')
            ->where('account_status', DriverProfile::STATUS_APPROVED)
            ->latest()
            ->get();

        $suspendedDrivers = DriverProfile::with('user')
            ->whereIn('account_status', [
                DriverProfile::STATUS_SUSPENDED,
                DriverProfile::STATUS_REJECTED,
            ])
            ->latest()
            ->get();

        return view('admin.drivers', compact(
            'pendingDrivers',
            'approvedDrivers',
            'suspendedDrivers'
        ));
    }

    /**
     * Approve a driver account.
     */
    public function approveDriver(DriverProfile $driver)
    {
        $driver->update(['account_status' => DriverProfile::STATUS_APPROVED]);
        $driver->user->update(['account_status' => User::STATUS_APPROVED]);

        return redirect()->route('admin.drivers')
            ->with('success', "{$driver->user->name} has been approved as a GEORX driver.");
    }

    /**
     * Suspend a driver account (reversible).
     */
    public function suspendDriver(DriverProfile $driver)
    {
        $driver->update([
            'account_status' => DriverProfile::STATUS_SUSPENDED,
            'is_online'      => false,   // Force offline
        ]);
        $driver->user->update(['account_status' => User::STATUS_SUSPENDED]);

        return redirect()->route('admin.drivers')
            ->with('success', "{$driver->user->name}'s driver account has been suspended.");
    }

    /**
     * Reject a driver application (permanent — keeps record for audit).
     */
    public function rejectDriver(DriverProfile $driver)
    {
        $driver->update([
            'account_status' => DriverProfile::STATUS_REJECTED,
            'is_online'      => false,
        ]);
        $driver->user->update(['account_status' => User::STATUS_REJECTED]);

        return redirect()->route('admin.drivers')
            ->with('success', "{$driver->user->name}'s driver application has been rejected.");
    }
}