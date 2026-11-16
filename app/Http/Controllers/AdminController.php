<?php

namespace App\Http\Controllers;

use App\Models\DriverProfile;
use App\Models\Medicine;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * Historical legacy driver roles decommissioned in favor of external courier dispatch (Grab / Maxim).
     * Preserved in the database for historical foreign-key integrity, but excluded from operational KPIs.
     */
    public const LEGACY_DRIVER_ROLES = ['driver', 'delivery_partner'];

    /**
     * Canonical operational role groups.
     */
    public const PATIENT_ROLES = ['customer', 'patient'];
    public const PHARMACY_OWNER_ROLES = ['pharmacy_owner', 'pharmacy_admin'];
    public const PHARMACIST_ROLES = ['pharmacist'];
    public const PHARMACY_STAFF_ROLES = ['pharmacy_staff'];
    public const ADMIN_ROLES = ['administrator', 'super_admin', 'admin'];

    /**
     * Terminal order statuses. All other statuses represent active in-progress orders.
     */
    public const TERMINAL_ORDER_STATUSES = ['delivered', 'rejected', 'cancelled'];

    /**
     * Clear cached dashboard metrics after Super Admin state changes.
     */
    protected function clearDashboardCache(): void
    {
        try {
            \Illuminate\Support\Facades\Cache::forget('admin_dashboard_data');
            \Illuminate\Support\Facades\Cache::forget('admin_dashboard_data_v2');
            \Illuminate\Support\Facades\Cache::forget('admin_dashboard_data_v3');
            \Illuminate\Support\Facades\Cache::forget('admin_dashboard_data_v4');
        } catch (\Throwable) {
        }
    }

    /**
     * Record a Super Admin governance action in the audit_logs table without failing the request if logging fails.
     */
    protected function recordAdminAuditLog(?int $pharmacyId, string $action, string $details): void
    {
        try {
            if ($pharmacyId !== null) {
                \App\Models\AuditLog::create([
                    'pharmacy_id' => $pharmacyId,
                    'user_id' => auth('admin')->id() ?? auth()->id(),
                    'action' => $action,
                    'details' => $details,
                ]);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Admin audit log write failed', [
                'action' => $action,
                'pharmacy_id' => $pharmacyId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Centralized user role classification helper.
     * Never misclassifies unknown roles as Patient.
     *
     * @return array{group: string, label: string, badge_class: string, icon: string, is_legacy_driver: bool}
     */
    public static function classifyUserRole(?string $role): array
    {
        $normalized = strtolower(trim((string) $role));

        if (in_array($normalized, self::ADMIN_ROLES, true)) {
            return [
                'group' => 'administrator',
                'label' => 'Super Admin',
                'badge_class' => 'bg-amber-50 text-amber-800 border-amber-200',
                'icon' => 'fas fa-shield-alt',
                'is_legacy_driver' => false,
            ];
        }

        if (in_array($normalized, self::PHARMACY_OWNER_ROLES, true)) {
            return [
                'group' => 'pharmacy_owner',
                'label' => 'Pharmacy Owner',
                'badge_class' => 'bg-emerald-50 text-[#2F7E6A] border-emerald-200',
                'icon' => 'fas fa-store',
                'is_legacy_driver' => false,
            ];
        }

        if (in_array($normalized, self::PHARMACIST_ROLES, true)) {
            return [
                'group' => 'pharmacist',
                'label' => 'Pharmacist',
                'badge_class' => 'bg-teal-50 text-teal-700 border-teal-200',
                'icon' => 'fas fa-user-md',
                'is_legacy_driver' => false,
            ];
        }

        if (in_array($normalized, self::PHARMACY_STAFF_ROLES, true)) {
            return [
                'group' => 'pharmacy_staff',
                'label' => 'Pharmacy Staff',
                'badge_class' => 'bg-cyan-50 text-cyan-700 border-cyan-200',
                'icon' => 'fas fa-id-badge',
                'is_legacy_driver' => false,
            ];
        }

        if (in_array($normalized, self::PATIENT_ROLES, true)) {
            return [
                'group' => 'patient',
                'label' => 'Patient / Customer',
                'badge_class' => 'bg-sky-50 text-sky-700 border-sky-200',
                'icon' => 'fas fa-user',
                'is_legacy_driver' => false,
            ];
        }

        if (in_array($normalized, self::LEGACY_DRIVER_ROLES, true)) {
            return [
                'group' => 'legacy_driver',
                'label' => 'Historical Driver (Archived)',
                'badge_class' => 'bg-slate-100 text-slate-600 border-slate-300',
                'icon' => 'fas fa-archive',
                'is_legacy_driver' => true,
            ];
        }

        return [
            'group' => 'unclassified',
            'label' => $normalized !== '' ? 'Unclassified (' . $normalized . ')' : 'Unclassified',
            'badge_class' => 'bg-rose-50 text-rose-700 border-rose-200',
            'icon' => 'fas fa-question-circle',
            'is_legacy_driver' => false,
        ];
    }

    // =============================================
    // DASHBOARD (PHASE 1 HARDENED)
    // =============================================

    public function dashboard()
    {
        $computeDashboardMetrics = function () {
            $pharmacyAdminRoles = array_merge(
                self::PHARMACY_OWNER_ROLES,
                self::PHARMACIST_ROLES,
                self::PHARMACY_STAFF_ROLES
            );
            $operationalRoleSet = array_merge(
                self::PATIENT_ROLES,
                $pharmacyAdminRoles,
                self::ADMIN_ROLES
            );

            $operationalUsersQuery = User::whereIn('role', $operationalRoleSet);

            $totalOperationalUsers = (clone $operationalUsersQuery)->count();
            $verifiedOperationalUsers = (clone $operationalUsersQuery)
                ->where(function ($q) {
                    $q->whereNotNull('email_verified_at')
                        ->orWhereIn('account_status', ['active', 'approved']);
                })
                ->where('account_status', '!=', 'pending_verification')
                ->count();
            $pendingVerificationUsers = (clone $operationalUsersQuery)
                ->where('account_status', 'pending_verification')
                ->count();
            $activeOperationalUsers = (clone $operationalUsersQuery)
                ->whereIn('account_status', ['active', 'approved'])
                ->count();
            $suspendedOrInactiveUsers = (clone $operationalUsersQuery)
                ->whereIn('account_status', ['suspended', 'rejected', 'inactive'])
                ->count();
            $legacyDriversExcluded = User::whereIn('role', self::LEGACY_DRIVER_ROLES)->count();

            // Core entity counts
            $stats = [
                'pharmacies' => Pharmacy::where('status', '!=', 'rejected')->count(),
                'pending_pharmacies' => Pharmacy::where('status', 'pending')->count(),
                'active_pharmacies' => Pharmacy::where('status', 'approved')->where('is_active', true)->count(),
                'suspended_pharmacies' => Pharmacy::where('status', 'suspended')->count(),
                'inactive_approved_pharmacies' => Pharmacy::where('status', 'approved')->where('is_active', false)->count(),
                'medicines' => Medicine::count(),
                'users' => $totalOperationalUsers,
                'verified_users' => $verifiedOperationalUsers,
                'pending_verification_users' => $pendingVerificationUsers,
                'active_users' => $activeOperationalUsers,
                'suspended_users' => $suspendedOrInactiveUsers,
                'legacy_drivers_excluded' => $legacyDriversExcluded,
            ];

            // Reconciled Order & Financial stats: Delivered + Active + Rejected/Cancelled = Total
            $totalOrders = \App\Models\Order::count();
            $deliveredOrders = \App\Models\Order::where('status', 'delivered')->count();
            $rejectedOrders = \App\Models\Order::where('status', 'rejected')->count();
            $cancelledOrders = \App\Models\Order::where('status', 'cancelled')->count();
            $rejectedOrCancelledOrders = $rejectedOrders + $cancelledOrders;
            $pendingOrders = \App\Models\Order::whereNotIn('status', self::TERMINAL_ORDER_STATUSES)->count();

            $completedOrderValue = round((float) \App\Models\Order::where('status', 'delivered')->sum('total_amount'), 2);
            $estimatedDeliveryFees = round((float) \App\Models\Order::where('status', 'delivered')
                ->sum(\Illuminate\Support\Facades\DB::raw('COALESCE(delivery_fee, 0)')), 2);
            $recordedActualCourierFees = round((float) \App\Models\Order::where('status', 'delivered')
                ->whereNotNull('actual_courier_fee')
                ->sum('actual_courier_fee'), 2);
            $recordedActualCourierCount = \App\Models\Order::where('status', 'delivered')
                ->whereNotNull('actual_courier_fee')
                ->count();
            $medicineSalesGmv = round(max(0, $completedOrderValue - $estimatedDeliveryFees), 2);
            $platformRevenue = $completedOrderValue;

            // Strict 3-Role Operational User Distribution (excludes drivers/legacy accounts)
            $roleCounts = [
                'Customers'      => User::whereIn('role', self::PATIENT_ROLES)->count(),
                'Pharmacy Admin' => User::whereIn('role', $pharmacyAdminRoles)->count(),
                'Administrator'  => User::whereIn('role', self::ADMIN_ROLES)->count(),
            ];

            // Monthly platform growth (last 6 calendar months in Asia/Manila, excluding legacy drivers)
            $tz = config('app.timezone', 'Asia/Manila');
            $nowTz = now($tz)->startOfMonth();
            $monthlyGrowth = [];
            for ($i = 5; $i >= 0; $i--) {
                $monthStartLocal = $nowTz->copy()->subMonths($i)->startOfMonth();
                $monthEndLocal = $monthStartLocal->copy()->endOfMonth();
                $monthStartUtc = $monthStartLocal->copy()->setTimezone('UTC');
                $monthEndUtc = $monthEndLocal->copy()->setTimezone('UTC');

                $monthlyGrowth[] = [
                    'month' => $monthStartLocal->format('M Y'),
                    'users' => User::whereIn('role', $operationalRoleSet)
                        ->whereBetween('created_at', [$monthStartUtc, $monthEndUtc])
                        ->count(),
                    'orders' => \App\Models\Order::whereBetween('created_at', [$monthStartUtc, $monthEndUtc])
                        ->count(),
                ];
            }

            // Most Searched Medicines (case-insensitive normalized, min 3 chars)
            $mostSearched = \App\Models\SearchLog::query()
                ->selectRaw('LOWER(TRIM(term)) as normalized_term, COUNT(*) as search_count')
                ->whereNotNull('term')
                ->whereRaw('LENGTH(TRIM(term)) >= 3')
                ->groupBy(\Illuminate\Support\Facades\DB::raw('LOWER(TRIM(term))'))
                ->orderByDesc('search_count')
                ->orderBy('normalized_term')
                ->limit(8)
                ->get()
                ->map(function ($row) {
                    $row->term = \Illuminate\Support\Str::title($row->normalized_term);
                    return $row;
                });

            // Most Ordered Medicines (completed/delivered orders only for actual units sold)
            $mostOrdered = \App\Models\OrderItem::query()
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('orders.status', 'delivered')
                ->select('order_items.medicine_id')
                ->selectRaw('SUM(order_items.quantity) as total_qty, COUNT(DISTINCT order_items.order_id) as order_count')
                ->groupBy('order_items.medicine_id')
                ->orderByDesc('total_qty')
                ->orderByDesc('order_count')
                ->limit(8)
                ->with('medicine:id,brand_name,generic_name,drug_category,dosage_form,strength')
                ->get();

            // Inventory Status across active, approved pharmacies (used for Reports cross-check)
            $activePharmacyInventoryBase = \Illuminate\Support\Facades\DB::table('pharmacy_medicine')
                ->join('pharmacies', 'pharmacy_medicine.pharmacy_id', '=', 'pharmacies.id')
                ->where('pharmacies.status', 'approved')
                ->where('pharmacies.is_active', true);

            $lowStockCount = (clone $activePharmacyInventoryBase)
                ->where('pharmacy_medicine.quantity_on_hand', '>', 0)
                ->whereRaw('pharmacy_medicine.quantity_on_hand <= COALESCE(pharmacy_medicine.reorder_level, 10)')
                ->count();

            $outOfStockCount = (clone $activePharmacyInventoryBase)
                ->where('pharmacy_medicine.quantity_on_hand', '<=', 0)
                ->count();

            return compact(
                'stats',
                'totalOrders',
                'deliveredOrders',
                'pendingOrders',
                'rejectedOrders',
                'cancelledOrders',
                'rejectedOrCancelledOrders',
                'platformRevenue',
                'completedOrderValue',
                'medicineSalesGmv',
                'estimatedDeliveryFees',
                'recordedActualCourierFees',
                'recordedActualCourierCount',
                'roleCounts',
                'monthlyGrowth',
                'mostSearched',
                'mostOrdered',
                'lowStockCount',
                'outOfStockCount'
            );
        };

        try {
            $cachedDashboard = \Illuminate\Support\Facades\Cache::remember('admin_dashboard_data_v4', 60, $computeDashboardMetrics);
        } catch (\Throwable) {
            $cachedDashboard = $computeDashboardMetrics();
        }

        // Recent orders live (not cached for freshness)
        $recentOrders = \App\Models\Order::with('user:id,name', 'pharmacy:id,name', 'items')
            ->latest()
            ->take(8)
            ->get();

        return view('admin.dashboard', array_merge($cachedDashboard, ['recentOrders' => $recentOrders]));
    }

    // =============================================
    // LIGHTWEIGHT ADMIN REPORTS (RECONCILED WITH DASHBOARD)
    // =============================================

    public function reports()
    {
        // Summary counts reconciled with Dashboard
        $totalUsers = User::whereNotIn('role', self::LEGACY_DRIVER_ROLES)->count();
        $totalPharmacies = Pharmacy::where('status', 'approved')->where('is_active', true)->count();
        $totalMedicines = Medicine::count();
        $totalOrders = \App\Models\Order::count();
        $pendingOrders = \App\Models\Order::whereNotIn('status', self::TERMINAL_ORDER_STATUSES)->count();
        $completedOrders = \App\Models\Order::where('status', 'delivered')->count();
        $rejectedOrCancelledOrders = \App\Models\Order::whereIn('status', ['rejected', 'cancelled'])->count();

        $completedOrderValue = round((float) \App\Models\Order::where('status', 'delivered')->sum('total_amount'), 2);
        $estimatedDeliveryFees = round((float) \App\Models\Order::where('status', 'delivered')
            ->sum(\Illuminate\Support\Facades\DB::raw('COALESCE(delivery_fee, 0)')), 2);
        $recordedActualCourierFees = round((float) \App\Models\Order::where('status', 'delivered')
            ->whereNotNull('actual_courier_fee')
            ->sum('actual_courier_fee'), 2);
        $recordedActualCourierCount = \App\Models\Order::where('status', 'delivered')
            ->whereNotNull('actual_courier_fee')
            ->count();
        $medicineSalesGmv = round(max(0, $completedOrderValue - $estimatedDeliveryFees), 2);

        // Most Ordered Medicines (top 15 — delivered orders only)
        $mostOrdered = \App\Models\OrderItem::query()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.status', 'delivered')
            ->select('order_items.medicine_id')
            ->selectRaw('SUM(order_items.quantity) as total_qty, COUNT(DISTINCT order_items.order_id) as order_count')
            ->groupBy('order_items.medicine_id')
            ->orderByDesc('total_qty')
            ->orderByDesc('order_count')
            ->limit(15)
            ->with('medicine:id,brand_name,generic_name,drug_category,dosage_form,strength')
            ->get();

        // Most Searched Medicines (normalized, min 3 chars)
        $mostSearched = \App\Models\SearchLog::query()
            ->selectRaw('LOWER(TRIM(term)) as normalized_term, COUNT(*) as search_count')
            ->whereNotNull('term')
            ->whereRaw('LENGTH(TRIM(term)) >= 3')
            ->groupBy(\Illuminate\Support\Facades\DB::raw('LOWER(TRIM(term))'))
            ->orderByDesc('search_count')
            ->orderBy('normalized_term')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $row->term = \Illuminate\Support\Str::title($row->normalized_term);
                return $row;
            });

        // Unified Low Stock & Out of Stock across active, approved pharmacies using COALESCE(reorder_level, 10)
        $activePharmacyInventoryBase = \Illuminate\Support\Facades\DB::table('pharmacy_medicine')
            ->join('medicines', 'pharmacy_medicine.medicine_id', '=', 'medicines.id')
            ->join('pharmacies', 'pharmacy_medicine.pharmacy_id', '=', 'pharmacies.id')
            ->where('pharmacies.status', 'approved')
            ->where('pharmacies.is_active', true);

        $lowStockCount = (clone $activePharmacyInventoryBase)
            ->where('pharmacy_medicine.quantity_on_hand', '>', 0)
            ->whereRaw('pharmacy_medicine.quantity_on_hand <= COALESCE(pharmacy_medicine.reorder_level, 10)')
            ->count();

        $lowStockMedicines = (clone $activePharmacyInventoryBase)
            ->where('pharmacy_medicine.quantity_on_hand', '>', 0)
            ->whereRaw('pharmacy_medicine.quantity_on_hand <= COALESCE(pharmacy_medicine.reorder_level, 10)')
            ->select(
                'medicines.brand_name',
                'medicines.generic_name',
                'pharmacies.id as pharmacy_id',
                'pharmacies.name as pharmacy_name',
                'pharmacy_medicine.quantity_on_hand',
                'pharmacy_medicine.reorder_level',
                'pharmacy_medicine.selling_price'
            )
            ->orderBy('pharmacy_medicine.quantity_on_hand')
            ->limit(30)
            ->get();

        $outOfStockCount = (clone $activePharmacyInventoryBase)
            ->where('pharmacy_medicine.quantity_on_hand', '<=', 0)
            ->count();

        return view('admin.reports', compact(
            'totalUsers',
            'totalPharmacies',
            'totalMedicines',
            'totalOrders',
            'pendingOrders',
            'completedOrders',
            'rejectedOrCancelledOrders',
            'completedOrderValue',
            'medicineSalesGmv',
            'estimatedDeliveryFees',
            'recordedActualCourierFees',
            'recordedActualCourierCount',
            'mostOrdered',
            'mostSearched',
            'lowStockCount',
            'lowStockMedicines',
            'outOfStockCount'
        ));
    }

    // =============================================
    // PHARMACY GOVERNANCE WORKFLOW (PHASE 4 HARDENED)
    // =============================================

    public function pharmacies(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $statusFilter = strtolower(trim((string) $request->query('status', 'all')));
        $sort = strtolower(trim((string) $request->query('sort', 'newest')));

        if (!in_array($statusFilter, ['all', 'approved', 'pending', 'suspended', 'inactive', 'rejected'], true)) {
            $statusFilter = 'all';
        }
        if (!in_array($sort, ['newest', 'oldest', 'name_asc', 'name_desc', 'updated_desc'], true)) {
            $sort = 'newest';
        }

        $query = Pharmacy::query()->withCount('medicines');

        if ($search !== '') {
            $like = '%' . mb_strtolower($search) . '%';
            $query->where(function ($q) use ($like, $search) {
                $q->whereRaw('LOWER(COALESCE(name, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(owner_name, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(phone, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(address, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(license_number, \'\')) LIKE ?', [$like]);
                if (ctype_digit($search)) {
                    $q->orWhere('id', (int) $search);
                }
            });
        }

        match ($statusFilter) {
            'approved' => $query->where('status', 'approved')->where('is_active', true),
            'pending' => $query->where('status', 'pending'),
            'suspended' => $query->where('status', 'suspended'),
            'inactive' => $query->where('status', 'approved')->where('is_active', false),
            'rejected' => $query->where('status', 'rejected'),
            default => null,
        };

        match ($sort) {
            'oldest' => $query->orderBy('created_at', 'asc'),
            'name_asc' => $query->orderBy('name', 'asc'),
            'name_desc' => $query->orderBy('name', 'desc'),
            'updated_desc' => $query->orderBy('updated_at', 'desc'),
            default => $query->orderBy('created_at', 'desc'),
        };

        $pharmacies = $query->paginate(9)->withQueryString();

        $statusCounts = [
            'all' => Pharmacy::count(),
            'approved' => Pharmacy::where('status', 'approved')->where('is_active', true)->count(),
            'pending' => Pharmacy::where('status', 'pending')->count(),
            'suspended' => Pharmacy::where('status', 'suspended')->count(),
            'inactive' => Pharmacy::where('status', 'approved')->where('is_active', false)->count(),
            'rejected' => Pharmacy::where('status', 'rejected')->count(),
        ];

        // Retain collection bindings for backward compatibility
        $pendingPharmacies = Pharmacy::where('status', 'pending')->latest()->get();
        $activePharmacies = Pharmacy::where('status', 'approved')->where('is_active', true)->latest()->get();
        $rejectedPharmacies = Pharmacy::whereIn('status', ['rejected', 'suspended'])->latest()->get();

        return view('admin.pharmacies', compact(
            'pharmacies',
            'statusCounts',
            'search',
            'statusFilter',
            'sort',
            'pendingPharmacies',
            'activePharmacies',
            'rejectedPharmacies'
        ));
    }

    public function reviewPharmacy(Pharmacy $pharmacy)
    {
        $isInsideAlijis = \App\Services\GeofenceService::isInsideAlijis(
            (float) $pharmacy->latitude,
            (float) $pharmacy->longitude
        );
        $ownerAccount = User::where('pharmacy_id', $pharmacy->id)->first();
        $auditHistory = \App\Models\AuditLog::where('pharmacy_id', $pharmacy->id)
            ->with('user')
            ->latest()
            ->take(20)
            ->get();

        return view('admin.pharmacy_review', compact('pharmacy', 'isInsideAlijis', 'ownerAccount', 'auditHistory'));
    }

    public function approvePharmacy(Request $request, Pharmacy $pharmacy)
    {
        if ($pharmacy->status === 'approved' && $pharmacy->is_approved) {
            return redirect()->route('admin.pharmacies')
                ->with('error', "{$pharmacy->name} is already approved.");
        }

        if ($pharmacy->status === 'suspended' && $pharmacy->is_approved) {
            return redirect()->route('admin.pharmacies', ['status' => 'suspended'])
                ->with('error', "{$pharmacy->name} is a suspended partner pharmacy. Use the Restore action instead of initial approval.");
        }

        if (!\App\Services\GeofenceService::isInsideAlijis((float) $pharmacy->latitude, (float) $pharmacy->longitude)) {
            return back()->with('error', "Cannot approve {$pharmacy->name} because its coordinates are outside the Barangay Alijis boundary.");
        }

        $pharmacy->update([
            'is_approved' => true,
            'is_active' => true,
            'status' => 'approved',
        ]);

        User::where('pharmacy_id', $pharmacy->id)
            ->whereIn('role', self::PHARMACY_OWNER_ROLES)
            ->whereIn('account_status', ['pending_review', 'rejected', 'suspended'])
            ->update(['account_status' => User::STATUS_ACTIVE]);

        $this->recordAdminAuditLog(
            $pharmacy->id,
            'Pharmacy Approved',
            "Super Admin approved {$pharmacy->name} (ID #{$pharmacy->id}) within Barangay Alijis geofence."
        );
        $this->clearDashboardCache();

        return redirect()->route('admin.pharmacies')->with('success', "{$pharmacy->name} has been approved and is now live!");
    }

    public function rejectPharmacy(Request $request, Pharmacy $pharmacy)
    {
        if ($pharmacy->status === 'approved' && $pharmacy->is_approved) {
            return back()->with('error', "Cannot reject an already approved partner pharmacy ({$pharmacy->name}). Use Suspend Account instead.");
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $reason = trim((string) ($validated['reason'] ?? 'Application did not meet GEORX partner verification requirements.'));

        // Non-destructive rejection: preserve pharmacy and user records for audit integrity
        $pharmacy->update([
            'is_approved' => false,
            'is_active' => false,
            'status' => 'rejected',
        ]);

        User::where('pharmacy_id', $pharmacy->id)
            ->whereIn('role', self::PHARMACY_OWNER_ROLES)
            ->update(['account_status' => User::STATUS_REJECTED]);

        $this->recordAdminAuditLog(
            $pharmacy->id,
            'Pharmacy Application Rejected',
            "Super Admin rejected application for {$pharmacy->name} (ID #{$pharmacy->id}). Reason: {$reason}"
        );
        $this->clearDashboardCache();

        return redirect()->route('admin.pharmacies', ['status' => 'rejected'])
            ->with('success', "{$pharmacy->name}'s application has been marked as rejected and preserved in audit history.");
    }

    public function suspendPharmacy(Request $request, Pharmacy $pharmacy)
    {
        if ($pharmacy->status === 'suspended') {
            return back()->with('error', "{$pharmacy->name} is already suspended.");
        }

        if ($pharmacy->status === 'rejected' || !$pharmacy->is_approved) {
            return back()->with('error', "Only approved partner pharmacies can be suspended.");
        }

        $validated = $request->validate([
            'reason' => 'required|string|min:5|max:500',
        ], [
            'reason.required' => 'Please provide a reason for suspending this pharmacy.',
            'reason.min' => 'The suspension reason must be at least 5 characters.',
        ]);

        $reason = trim($validated['reason']);

        $pharmacy->update([
            'is_active' => false,
            'status' => 'suspended',
        ]);

        $this->recordAdminAuditLog(
            $pharmacy->id,
            'Pharmacy Suspended',
            "Super Admin suspended {$pharmacy->name} (ID #{$pharmacy->id}). Reason: {$reason}"
        );
        $this->clearDashboardCache();

        return redirect()->route('admin.pharmacies', ['status' => 'suspended'])
            ->with('success', "{$pharmacy->name} has been safely suspended from the public map.");
    }

    public function restorePharmacy(Request $request, Pharmacy $pharmacy)
    {
        if ($pharmacy->status === 'rejected') {
            return back()->with('error', "Cannot restore {$pharmacy->name} because rejected applications cannot be reactivated via restoration.");
        }

        if ($pharmacy->status !== 'suspended') {
            return back()->with('error', "{$pharmacy->name} is not currently suspended.");
        }

        if (!$pharmacy->is_approved) {
            return back()->with('error', "Cannot restore {$pharmacy->name} because it was never approved as a verified partner pharmacy.");
        }

        if (!\App\Services\GeofenceService::isInsideAlijis((float) $pharmacy->latitude, (float) $pharmacy->longitude)) {
            return back()->with('error', "Cannot restore {$pharmacy->name} because its coordinates are outside the Barangay Alijis service boundary.");
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $reason = trim((string) ($validated['reason'] ?? 'Compliance verified; partner account restored by Super Admin.'));
        if ($reason === '') {
            $reason = 'Compliance verified; partner account restored by Super Admin.';
        }

        $pharmacy->update([
            'status' => 'approved',
            'is_approved' => true,
            'is_active' => true,
        ]);

        $this->recordAdminAuditLog(
            $pharmacy->id,
            'Pharmacy Restored',
            "Super Admin restored {$pharmacy->name} (ID #{$pharmacy->id}) to active approved status. Note: {$reason}"
        );
        $this->clearDashboardCache();

        return redirect()->route('admin.pharmacies', ['status' => 'approved'])
            ->with('success', "{$pharmacy->name} has been restored and is now active on the public map.");
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

        if (!\App\Services\GeofenceService::isInsideAlijis((float) $validated['latitude'], (float) $validated['longitude'])) {
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

        $oldCoverPhoto = $pharmacy->cover_photo;
        $uploadedCoverPhoto = null;

        if ($request->hasFile('cover_photo')) {
            $storageService = app(\App\Services\SupabaseStorageService::class);

            try {
                $uploadedCoverPhoto = $storageService->upload(
                    $request->file('cover_photo'),
                    \App\Services\SupabaseStorageService::BUCKET_PHARMACY_COVERS
                );
                $data['cover_photo'] = $uploadedCoverPhoto;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Admin pharmacy cover upload failed', [
                    'pharmacy_id' => $pharmacy->id,
                    'error' => $e->getMessage(),
                ]);

                return redirect()->back()
                    ->withInput($request->except(['cover_photo']))
                    ->withErrors(['cover_photo' => 'The storefront cover photo could not be uploaded. Please try again.']);
            }
        }

        $pharmacy->update($data);

        if ($uploadedCoverPhoto !== null && $oldCoverPhoto && $oldCoverPhoto !== $uploadedCoverPhoto) {
            try {
                $storageService->delete(\App\Services\SupabaseStorageService::BUCKET_PHARMACY_COVERS, $oldCoverPhoto);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Previous admin pharmacy cover deletion failed', [
                    'pharmacy_id' => $pharmacy->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->recordAdminAuditLog(
            $pharmacy->id,
            'Pharmacy Profile Updated',
            "Super Admin updated profile details for {$pharmacy->name} (ID #{$pharmacy->id})."
        );
        $this->clearDashboardCache();

        return redirect()->route('admin.pharmacies')->with('success', 'Pharmacy updated successfully!');
    }

    public function deletePharmacy(Pharmacy $pharmacy)
    {
        $hasOrders = \App\Models\Order::where('pharmacy_id', $pharmacy->id)->exists();
        $hasInventory = $pharmacy->medicines()->exists();

        if ($pharmacy->is_approved || $hasOrders || $hasInventory) {
            return back()->with('error', "Cannot permanently delete {$pharmacy->name} because historical orders, inventory, or approval records exist. Use Suspend Account instead to preserve audit integrity.");
        }

        User::where('pharmacy_id', $pharmacy->id)
            ->whereNotIn('role', self::ADMIN_ROLES)
            ->update(['account_status' => User::STATUS_REJECTED]);

        $pharmacy->update([
            'status' => 'rejected',
            'is_approved' => false,
            'is_active' => false,
        ]);

        $this->clearDashboardCache();

        return redirect()->route('admin.pharmacies')->with('success', "{$pharmacy->name} has been archived as rejected without destroying historical records.");
    }

    // =============================================
    // READ-ONLY VIEWS FOR SUPER ADMIN (PHASES 2 & 3)
    // =============================================

    /**
     * Phase 3: Read-only Pharmacy Inventory Audit (/admin/pharmacies/{pharmacy}/inventory).
     * Super Admin can audit a specific pharmacy's inventory (active or suspended), but cannot mutate stock.
     */
    public function pharmacyInventory(Request $request, Pharmacy $pharmacy)
    {
        $search = trim((string) $request->query('search', ''));
        $stockStatus = strtolower(trim((string) $request->query('stock_status', 'all')));
        $sort = strtolower(trim((string) $request->query('sort', 'name_asc')));

        if (!in_array($stockStatus, ['all', 'in_stock', 'low_stock', 'out_of_stock'], true)) {
            $stockStatus = 'all';
        }
        if (!in_array($sort, ['name_asc', 'name_desc', 'stock_asc', 'stock_desc', 'price_asc', 'price_desc'], true)) {
            $sort = 'name_asc';
        }

        $allItems = $pharmacy->medicines()->get();
        $inventorySummary = [
            'total_items' => $allItems->count(),
            'in_stock_count' => $allItems->filter(function ($m) {
                $qty = (int) ($m->pivot->quantity_on_hand ?? 0);
                $reorder = (int) ($m->pivot->reorder_level ?? 10);
                return $qty > $reorder;
            })->count(),
            'low_stock_count' => $allItems->filter(function ($m) {
                $qty = (int) ($m->pivot->quantity_on_hand ?? 0);
                $reorder = (int) ($m->pivot->reorder_level ?? 10);
                return $qty > 0 && $qty <= $reorder;
            })->count(),
            'out_of_stock_count' => $allItems->filter(function ($m) {
                return (int) ($m->pivot->quantity_on_hand ?? 0) <= 0;
            })->count(),
            'total_units' => (int) $allItems->sum(fn ($m) => max(0, (int) ($m->pivot->quantity_on_hand ?? 0))),
        ];

        $query = $pharmacy->medicines();

        if ($search !== '') {
            $like = '%' . mb_strtolower($search) . '%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(COALESCE(medicines.brand_name, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(medicines.generic_name, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(medicines.drug_category, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(medicines.dosage_form, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(pharmacy_medicine.batch_number, \'\')) LIKE ?', [$like]);
            });
        }

        match ($stockStatus) {
            'in_stock' => $query->whereRaw('pharmacy_medicine.quantity_on_hand > COALESCE(pharmacy_medicine.reorder_level, 10)'),
            'low_stock' => $query->where('pharmacy_medicine.quantity_on_hand', '>', 0)
                ->whereRaw('pharmacy_medicine.quantity_on_hand <= COALESCE(pharmacy_medicine.reorder_level, 10)'),
            'out_of_stock' => $query->where('pharmacy_medicine.quantity_on_hand', '<=', 0),
            default => null,
        };

        match ($sort) {
            'name_desc' => $query->orderBy('medicines.generic_name', 'desc'),
            'stock_asc' => $query->orderBy('pharmacy_medicine.quantity_on_hand', 'asc'),
            'stock_desc' => $query->orderBy('pharmacy_medicine.quantity_on_hand', 'desc'),
            'price_asc' => $query->orderBy('pharmacy_medicine.selling_price', 'asc'),
            'price_desc' => $query->orderBy('pharmacy_medicine.selling_price', 'desc'),
            default => $query->orderBy('medicines.generic_name', 'asc'),
        };

        $inventory = $query->paginate(20)->withQueryString();
        $ownerAccount = User::where('pharmacy_id', $pharmacy->id)->first();

        return view('admin.inventory', compact(
            'pharmacy',
            'inventory',
            'inventorySummary',
            'ownerAccount',
            'search',
            'stockStatus',
            'sort'
        ));
    }

    /**
     * Phase 2: Read-only Master Medicine Catalog (/admin/medicines).
     */
    public function medicines(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $category = trim((string) $request->query('category', ''));
        $dosageForm = trim((string) $request->query('dosage_form', ''));
        $availability = strtolower(trim((string) $request->query('availability', 'all')));
        $sort = strtolower(trim((string) $request->query('sort', 'generic_asc')));

        if (!in_array($availability, ['all', 'in_stock', 'out_of_stock', 'prescription_required'], true)) {
            $availability = 'all';
        }
        if (!in_array($sort, ['generic_asc', 'generic_desc', 'brand_asc', 'newest'], true)) {
            $sort = 'generic_asc';
        }

        $categories = Medicine::query()
            ->whereNotNull('drug_category')
            ->where('drug_category', '!=', '')
            ->select('drug_category')
            ->distinct()
            ->orderBy('drug_category')
            ->pluck('drug_category');

        $dosageForms = Medicine::query()
            ->whereNotNull('dosage_form')
            ->where('dosage_form', '!=', '')
            ->select('dosage_form')
            ->distinct()
            ->orderBy('dosage_form')
            ->pluck('dosage_form');

        $query = Medicine::query()->with(['pharmacies' => function ($q) {
            $q->where('pharmacies.status', 'approved')
                ->where('pharmacies.is_active', true);
        }]);

        if ($search !== '') {
            $like = '%' . mb_strtolower($search) . '%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(COALESCE(brand_name, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(generic_name, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(drug_category, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(therapeutic_class, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(dosage_form, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(strength, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(medicine_code, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(fda_registration_no, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', [$like]);
            });
        }

        if ($category !== '') {
            $query->where('drug_category', $category);
        }

        if ($dosageForm !== '') {
            $query->where('dosage_form', $dosageForm);
        }

        if ($availability === 'in_stock') {
            $query->whereHas('pharmacies', function ($q) {
                $q->where('pharmacies.status', 'approved')
                    ->where('pharmacies.is_active', true)
                    ->where('pharmacy_medicine.quantity_on_hand', '>', 0);
            });
        } elseif ($availability === 'out_of_stock') {
            $query->whereDoesntHave('pharmacies', function ($q) {
                $q->where('pharmacies.status', 'approved')
                    ->where('pharmacies.is_active', true)
                    ->where('pharmacy_medicine.quantity_on_hand', '>', 0);
            });
        } elseif ($availability === 'prescription_required') {
            $query->where('prescription_required', true);
        }

        match ($sort) {
            'generic_desc' => $query->orderBy('generic_name', 'desc'),
            'brand_asc' => $query->orderByRaw('COALESCE(NULLIF(brand_name, \'\'), generic_name) ASC'),
            'newest' => $query->orderBy('created_at', 'desc'),
            default => $query->orderBy('generic_name', 'asc'),
        };

        $medicines = $query->paginate(15)->withQueryString();

        $catalogStats = [
            'total_medicines' => Medicine::count(),
            'stocked_medicines' => Medicine::whereHas('pharmacies', function ($q) {
                $q->where('pharmacies.status', 'approved')
                    ->where('pharmacies.is_active', true)
                    ->where('pharmacy_medicine.quantity_on_hand', '>', 0);
            })->count(),
            'rx_medicines' => Medicine::where('prescription_required', true)->count(),
            'categories_count' => $categories->count(),
        ];

        return view('admin.medicines', compact(
            'medicines',
            'categories',
            'dosageForms',
            'catalogStats',
            'search',
            'category',
            'dosageForm',
            'availability',
            'sort'
        ));
    }

    // =============================================
    // USER MANAGEMENT (PHASE 5 HARDENED)
    // =============================================

    public function usersIndex(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $roleFilter = strtolower(trim((string) $request->query('role', 'all')));
        $statusFilter = strtolower(trim((string) $request->query('status', 'all')));
        $verificationFilter = strtolower(trim((string) $request->query('verification', 'all')));

        if (!in_array($roleFilter, ['all', 'patient', 'pharmacy_owner', 'pharmacist', 'pharmacy_staff', 'administrator', 'legacy_driver'], true)) {
            $roleFilter = 'all';
        }
        if (!in_array($statusFilter, ['all', 'active', 'pending_verification', 'suspended', 'rejected'], true)) {
            $statusFilter = 'all';
        }
        if (!in_array($verificationFilter, ['all', 'verified', 'unverified'], true)) {
            $verificationFilter = 'all';
        }

        $stats = [
            'pharmacies' => Pharmacy::where('status', '!=', 'rejected')->count(),
            'active_pharmacies' => Pharmacy::where('status', 'approved')->where('is_active', true)->count(),
            'users' => User::whereNotIn('role', self::LEGACY_DRIVER_ROLES)->count(),
            'verified_users' => User::whereNotIn('role', self::LEGACY_DRIVER_ROLES)
                ->where(function ($q) {
                    $q->whereNotNull('email_verified_at')
                        ->orWhereIn('account_status', ['active', 'approved']);
                })
                ->where('account_status', '!=', 'pending_verification')
                ->count(),
            'pending_verification_users' => User::whereNotIn('role', self::LEGACY_DRIVER_ROLES)
                ->where('account_status', 'pending_verification')
                ->count(),
            'suspended_users' => User::whereNotIn('role', self::LEGACY_DRIVER_ROLES)
                ->whereIn('account_status', ['suspended', 'rejected'])
                ->count(),
            'legacy_drivers_excluded' => User::whereIn('role', self::LEGACY_DRIVER_ROLES)->count(),
        ];

        $roleFilterCounts = [
            'all' => $stats['users'],
            'patient' => User::whereIn('role', self::PATIENT_ROLES)->count(),
            'pharmacy_owner' => User::whereIn('role', self::PHARMACY_OWNER_ROLES)->count(),
            'pharmacist' => User::whereIn('role', self::PHARMACIST_ROLES)->count(),
            'pharmacy_staff' => User::whereIn('role', self::PHARMACY_STAFF_ROLES)->count(),
            'administrator' => User::whereIn('role', self::ADMIN_ROLES)->count(),
            'legacy_driver' => $stats['legacy_drivers_excluded'],
        ];

        $query = User::query()->with('pharmacy:id,name,status,is_active');

        // Exclude historical legacy drivers by default unless explicitly viewing the archived driver filter
        if ($roleFilter === 'legacy_driver') {
            $query->whereIn('role', self::LEGACY_DRIVER_ROLES);
        } else {
            $query->whereNotIn('role', self::LEGACY_DRIVER_ROLES);

            match ($roleFilter) {
                'patient' => $query->whereIn('role', self::PATIENT_ROLES),
                'pharmacy_owner' => $query->whereIn('role', self::PHARMACY_OWNER_ROLES),
                'pharmacist' => $query->whereIn('role', self::PHARMACIST_ROLES),
                'pharmacy_staff' => $query->whereIn('role', self::PHARMACY_STAFF_ROLES),
                'administrator' => $query->whereIn('role', self::ADMIN_ROLES),
                default => null,
            };
        }

        if ($search !== '') {
            $like = '%' . mb_strtolower($search) . '%';
            $query->where(function ($q) use ($like, $search) {
                $q->whereRaw('LOWER(COALESCE(name, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(phone, \'\')) LIKE ?', [$like]);
                if (ctype_digit($search)) {
                    $q->orWhere('id', (int) $search);
                }
            });
        }

        match ($statusFilter) {
            'active' => $query->whereIn('account_status', ['active', 'approved']),
            'pending_verification' => $query->where('account_status', 'pending_verification'),
            'suspended' => $query->where('account_status', 'suspended'),
            'rejected' => $query->where('account_status', 'rejected'),
            default => null,
        };

        if ($verificationFilter === 'verified') {
            $query->where(function ($q) {
                $q->whereNotNull('email_verified_at')
                    ->orWhereIn('account_status', ['active', 'approved']);
            })->where('account_status', '!=', 'pending_verification');
        } elseif ($verificationFilter === 'unverified') {
            $query->where(function ($q) {
                $q->whereNull('email_verified_at')
                    ->orWhere('account_status', 'pending_verification');
            });
        }

        $usersList = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        return view('admin.users', compact(
            'stats',
            'roleFilterCounts',
            'usersList',
            'search',
            'roleFilter',
            'statusFilter',
            'verificationFilter'
        ));
    }

    public function toggleUserStatus(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $currentAdminId = (int) (auth('admin')->id() ?? auth()->id());

        if ((int) $user->id === $currentAdminId) {
            return back()->with('error', 'Security guard: You cannot suspend or deactivate your own Super Admin account.');
        }

        if (in_array(strtolower((string) $user->role), self::ADMIN_ROLES, true)) {
            $otherActiveAdmins = User::whereIn('role', self::ADMIN_ROLES)
                ->where('id', '!=', $user->id)
                ->whereIn('account_status', ['active', 'approved'])
                ->count();

            if ($otherActiveAdmins === 0) {
                return back()->with('error', 'Security guard: Cannot suspend the last active Super Admin account.');
            }
        }

        $validated = $request->validate([
            'action' => 'required|in:suspend,activate',
        ]);

        $newStatus = $validated['action'] === 'suspend' ? User::STATUS_SUSPENDED : User::STATUS_ACTIVE;
        $user->update(['account_status' => $newStatus]);

        $this->clearDashboardCache();

        $actionVerb = $newStatus === User::STATUS_SUSPENDED ? 'suspended' : 'reactivated';
        return back()->with('success', "User account for {$user->name} has been {$actionVerb}.");
    }

    public function destroyUser($id)
    {
        $user = User::findOrFail($id);
        $currentAdminId = (int) (auth('admin')->id() ?? auth()->id());

        // 1. Prevent Super Admin self-deletion
        if ((int) $user->id === $currentAdminId) {
            return redirect()->route('admin.users')
                ->with('error', 'Security guard: You cannot delete your own currently authenticated Super Admin account.');
        }

        // 2. Prevent deleting the last active Super Admin
        if (in_array(strtolower((string) $user->role), self::ADMIN_ROLES, true)) {
            $otherActiveAdmins = User::whereIn('role', self::ADMIN_ROLES)
                ->where('id', '!=', $user->id)
                ->whereIn('account_status', ['active', 'approved'])
                ->count();

            if ($otherActiveAdmins === 0) {
                return redirect()->route('admin.users')
                    ->with('error', 'Security guard: Cannot delete the last active Super Admin account.');
            }
        }

        // 3. Prevent hard-deleting users with critical historical relationships (orders, pharmacy link, conversations, driver profile)
        $hasOrders = \App\Models\Order::where('user_id', $user->id)
            ->orWhere('delivery_partner_id', $user->id)
            ->exists();
        $hasPharmacy = !empty($user->pharmacy_id);
        $hasConversations = \Illuminate\Support\Facades\DB::table('conversations')->where('user_id', $user->id)->exists();
        $hasDriverProfile = DriverProfile::where('user_id', $user->id)->exists();

        if ($hasOrders || $hasPharmacy || $hasConversations || $hasDriverProfile) {
            return redirect()->route('admin.users')
                ->with('error', "Cannot permanently delete {$user->name} because historical orders, pharmacy associations, or audit records exist. Use Suspend Account instead.");
        }

        $user->delete();
        $this->clearDashboardCache();

        return redirect()->route('admin.users')->with('success', 'Unlinked user profile deleted successfully from the system.');
    }

    // =============================================
    // PLATFORM SETTINGS (PHASE 6 HARDENED)
    // =============================================
    public function settings()
    {
        $settings = \App\Models\GlobalSetting::getSettings();
        return view('admin.settings', compact('settings'));
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'base_delivery_fee'         => 'required|numeric|min:0|max:1000',
            'delivery_per_km_fee'       => 'nullable|numeric|min:0|max:100',
            'delivery_minimum_fee'      => 'nullable|numeric|min:0|max:1000',
            'delivery_maximum_estimate' => 'nullable|numeric|min:0|max:1000',
        ]);

        $baseFee = (float) $validated['base_delivery_fee'];
        $minFee = isset($validated['delivery_minimum_fee']) ? (float) $validated['delivery_minimum_fee'] : $baseFee;
        $maxFee = isset($validated['delivery_maximum_estimate']) ? (float) $validated['delivery_maximum_estimate'] : 150.0;

        if ($minFee > $maxFee) {
            return back()->withErrors([
                'delivery_minimum_fee' => 'The minimum delivery fee cannot exceed the maximum estimated fee cap.',
            ])->withInput();
        }

        \App\Models\GlobalSetting::set('enable_new_registrations', $request->has('enable_new_registrations') ? '1' : '0');
        \App\Models\GlobalSetting::set('delivery_estimate_enabled', $request->has('delivery_estimate_enabled') ? '1' : '0');
        \App\Models\GlobalSetting::set('base_delivery_fee', number_format($baseFee, 2, '.', ''));
        \App\Models\GlobalSetting::set('delivery_base_fee', number_format($baseFee, 2, '.', ''));
        \App\Models\GlobalSetting::set('delivery_per_km_fee', number_format((float) ($validated['delivery_per_km_fee'] ?? 0.0), 2, '.', ''));
        \App\Models\GlobalSetting::set('delivery_minimum_fee', number_format($minFee, 2, '.', ''));
        \App\Models\GlobalSetting::set('delivery_maximum_estimate', number_format($maxFee, 2, '.', ''));

        return back()->with('success', 'Platform settings and delivery fee parameters updated successfully.');
    }

    // =============================================
    // LEGACY DRIVER MANAGEMENT (ARCHIVED COMPATIBILITY)
    // =============================================

    public function driversIndex()
    {
        $pendingDrivers = DriverProfile::with('user')
            ->where('account_status', DriverProfile::STATUS_PENDING_REVIEW)
            ->latest()
            ->get();

        $approvedDrivers = DriverProfile::with('user')
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

    public function approveDriver(DriverProfile $driver)
    {
        $driver->update(['account_status' => DriverProfile::STATUS_APPROVED]);
        $driver->user->update(['account_status' => User::STATUS_APPROVED]);

        return redirect()->route('admin.drivers')
            ->with('success', "{$driver->user->name} has been approved as a GEORX driver.");
    }

    public function suspendDriver(DriverProfile $driver)
    {
        $driver->update([
            'account_status' => DriverProfile::STATUS_SUSPENDED,
            'is_online'      => false,
        ]);
        $driver->user->update(['account_status' => User::STATUS_SUSPENDED]);

        return redirect()->route('admin.drivers')
            ->with('success', "{$driver->user->name}'s driver account has been suspended.");
    }

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