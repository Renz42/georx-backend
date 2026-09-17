<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Models\StockAlert;
use App\Models\User;
use App\Notifications\MedicineRestocked;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Notification;
use Barryvdh\DomPDF\Facade\Pdf;

class PortalController extends Controller
{
    // Get current pharmacy (scoped to logged-in admin)
    private function getMyPharmacy()
    {
        return Auth::user()->pharmacy;
    }

    // Dashboard
    public function dashboard()
    {
        $pharmacy = $this->getMyPharmacy();
        
        if (!$pharmacy) {
            return redirect('/login')->with('error', 'No pharmacy associated with your account.');
        }

        // Base stats
        $stats = [
            'medicines_in_stock' => $pharmacy->medicines()->wherePivot('quantity_on_hand', '>', 0)->count(),
            'low_stock' => $pharmacy->medicines()->wherePivot('quantity_on_hand', '>', 0)->wherePivot('quantity_on_hand', '<=', 10)->count(),
            'out_of_stock' => $pharmacy->medicines()->wherePivot('quantity_on_hand', 0)->count(),
            'restock_alerts' => \App\Models\StockAlert::where('pharmacy_id', $pharmacy->id)->where('is_active', true)->count(),
        ];

        // Revenue calculations (only counting delivered orders)
        $deliveredOrders = \App\Models\Order::where('pharmacy_id', $pharmacy->id)
            ->where('status', 'delivered')
            ->get();
            
        // Calculate pharmacy earnings (total_amount minus delivery_fee if applicable, or just sum of item prices)
        // Since total_amount = items subtotal + delivery_fee, pharmacy earning is just total_amount - delivery_fee
        $totalRevenue = $deliveredOrders->sum(function($order) {
            return $order->total_amount - $order->delivery_fee;
        });
        
        $todayRevenue = $deliveredOrders->where('created_at', '>=', now()->startOfDay())->sum(function($order) {
            return $order->total_amount - $order->delivery_fee;
        });

        // Top 5 Fast-Moving Medicines
        $topMedicines = \App\Models\OrderItem::selectRaw('medicine_id, SUM(quantity) as total_sold')
            ->whereHas('order', function($q) use ($pharmacy) {
                $q->where('pharmacy_id', $pharmacy->id)->where('status', 'delivered');
            })
            ->groupBy('medicine_id')
            ->orderByDesc('total_sold')
            ->with('medicine')
            ->take(5)
            ->get();

        // Monthly Sales Data for Chart (Last 6 Months)
        $monthlySales = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $revenue = \App\Models\Order::where('pharmacy_id', $pharmacy->id)
                ->where('status', 'delivered')
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->get()
                ->sum(function($order) {
                    return $order->total_amount - $order->delivery_fee;
                });
                
            $monthlySales[] = [
                'month' => $month->format('M Y'),
                'revenue' => $revenue
            ];
        }

        return view('pharmacy.dashboard', compact('pharmacy', 'stats', 'totalRevenue', 'todayRevenue', 'topMedicines', 'monthlySales'));
    }

    public function downloadReport()
    {
        $pharmacy = $this->getMyPharmacy();
        
        $deliveredOrders = \App\Models\Order::where('pharmacy_id', $pharmacy->id)
            ->where('status', 'delivered')
            ->get();
            
        $totalRevenue = $deliveredOrders->sum(function($order) {
            return $order->total_amount - $order->delivery_fee;
        });

        $topMedicines = \App\Models\OrderItem::selectRaw('medicine_id, SUM(quantity) as total_sold')
            ->whereHas('order', function($q) use ($pharmacy) {
                $q->where('pharmacy_id', $pharmacy->id)->where('status', 'delivered');
            })
            ->groupBy('medicine_id')
            ->orderByDesc('total_sold')
            ->with('medicine')
            ->take(10)
            ->get();
            
        $data = [
            'pharmacy' => $pharmacy,
            'totalRevenue' => $totalRevenue,
            'ordersCount' => $deliveredOrders->count(),
            'topMedicines' => $topMedicines,
            'date' => now()->format('F d, Y')
        ];

        $pdf = Pdf::loadView('portal.reports.sales-pdf', $data);
        return $pdf->download('Sales_Report_' . str_replace(' ', '_', $pharmacy->name) . '_' . now()->format('Y-m-d') . '.pdf');
    }

    public function auditLogs()
    {
        $pharmacy = $this->getMyPharmacy();
        
        $logs = \App\Models\AuditLog::where('pharmacy_id', $pharmacy->id)
            ->with('user')
            ->latest()
            ->paginate(15);
            
        return view('pharmacy.audit_logs', compact('pharmacy', 'logs'));
    }


    // Inventory management (only their pharmacy)
    public function inventory(Request $request)
    {
        $pharmacy = $this->getMyPharmacy();
        
        // PERFORMANCE FIX: Only load necessary fields for the dropdown
        $medicines = Medicine::select('id', 'generic_name', 'brand_name')->orderBy('generic_name')->get();

        $nowStr = now()->toDateString();
        $expiryThreshold = now()->addDays(90)->toDateString();

        // =============================================
        // 1. Compute expiry counts via SQL for better performance
        // =============================================
        $totalStock = $pharmacy->medicines()->count();
        
        $expiredCount = $pharmacy->medicines()
            ->whereNotNull('pharmacy_medicine.expiration_date')
            ->where('pharmacy_medicine.expiration_date', '<', $nowStr)
            ->count();
            
        $expiringCount = $pharmacy->medicines()
            ->where('pharmacy_medicine.expiration_date', '>=', $nowStr)
            ->where('pharmacy_medicine.expiration_date', '<=', $expiryThreshold)
            ->count();

        $outOfStockCount = $pharmacy->medicines()
            ->where('pharmacy_medicine.quantity_on_hand', '<=', 0)
            ->count();

        $lowStockCount = $pharmacy->medicines()
            ->where('pharmacy_medicine.quantity_on_hand', '>', 0)
            ->whereRaw('pharmacy_medicine.quantity_on_hand <= pharmacy_medicine.reorder_level')
            ->count();

        $expiryCounts = [
            'all' => $totalStock,
            'valid' => $totalStock - ($expiredCount + $expiringCount),
            'expiring' => $expiringCount,
            'expired' => $expiredCount,
        ];
        
        $stockCounts = [
            'out_of_stock' => $outOfStockCount,
            'low_stock' => $lowStockCount,
        ];

        // =============================================
        // 2. Build the filtered + searchable query
        // =============================================
        $filter = $request->get('filter', 'all');
        $search = $request->get('search', '');

        // Use the relationship so pivot data is automatically available
        $query = $pharmacy->medicines();

        // Apply expiry filter via pivot
        $nowStr = now()->toDateString();
        $expiryThreshold = now()->addDays(90)->toDateString();

        switch ($filter) {
            case 'expired':
                $query->where(function ($q) use ($nowStr) {
                    $q->where('pharmacy_medicine.expiration_date', '<', $nowStr)
                      ->whereNotNull('pharmacy_medicine.expiration_date');
                });
                break;
            case 'expiring':
                $query->where(function ($q) use ($nowStr, $expiryThreshold) {
                    $q->where('pharmacy_medicine.expiration_date', '>=', $nowStr)
                      ->where('pharmacy_medicine.expiration_date', '<=', $expiryThreshold);
                });
                break;
            case 'valid':
                $query->where(function ($q) use ($expiryThreshold) {
                    $q->where('pharmacy_medicine.expiration_date', '>', $expiryThreshold)
                      ->orWhereNull('pharmacy_medicine.expiration_date');
                });
                break;
            case 'low_stock':
                $query->whereRaw('pharmacy_medicine.quantity_on_hand <= pharmacy_medicine.reorder_level')
                      ->where('pharmacy_medicine.quantity_on_hand', '>', 0);
                break;
            case 'out_of_stock':
                $query->where('pharmacy_medicine.quantity_on_hand', '<=', 0);
                break;
            // 'all' — no filter
        }

        // Apply search filter
        if (!empty($search)) {
            $like = \Illuminate\Support\Facades\DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'like';
            $query->where(function ($q) use ($search, $like) {
                $q->where('medicines.generic_name', $like, "%{$search}%")
                  ->orWhere('medicines.brand_name', $like, "%{$search}%")
                  ->orWhere('pharmacy_medicine.batch_number', $like, "%{$search}%");
            });
        }

        $inventory = $query->paginate(10)->appends($request->query());

        return view('pharmacy.inventory', compact('pharmacy', 'medicines', 'inventory', 'expiryCounts', 'stockCounts', 'filter', 'search'));
    }

    // Add medicine to inventory
    public function addToInventory(Request $request)
    {
        $pharmacy = $this->getMyPharmacy();
        
        // Updated to include all our new professional inventory fields
        $validated = $request->validate([
            'medicine_id' => 'required|exists:medicines,id',
            'unit' => 'required|string', // Added unit validation
            'selling_price' => 'required|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'quantity_on_hand' => 'required|integer|min:0',
            'reorder_level' => 'nullable|integer|min:0',
            'batch_number' => 'nullable|string|max:100',
            'expiration_date' => 'nullable|date',
            'storage_condition' => 'nullable|string|max:255',
            'supplier' => 'nullable|string|max:255',
            'last_restocked_at' => 'nullable|date',
        ]);

        // Check if already exists in their store
        if ($pharmacy->medicines()->where('medicine_id', $validated['medicine_id'])->exists()) {
            return redirect('/portal/inventory')->with('error', 'Medicine already in inventory. Edit it instead.');
        }

        $pharmacy->medicines()->attach($validated['medicine_id'], [
            'unit' => $validated['unit'],
            'selling_price' => $validated['selling_price'],
            'purchase_price' => $validated['purchase_price'] ?? null,
            'quantity_on_hand' => $validated['quantity_on_hand'],
            'reorder_level' => $validated['reorder_level'] ?? 10,
            'batch_number' => $validated['batch_number'] ?? null,
            'expiration_date' => $validated['expiration_date'] ?? null,
            'storage_condition' => $validated['storage_condition'] ?? null,
            'supplier' => $validated['supplier'] ?? null,
            'last_restocked_at' => $validated['last_restocked_at'] ?? null,
            'is_available' => $validated['quantity_on_hand'] > 0,
        ]);

        if ($validated['quantity_on_hand'] > 0 || !empty($validated['batch_number'])) {
            app(\App\Services\InventoryService::class)->addBatch($pharmacy->id, $validated['medicine_id'], [
                'batch_number' => $validated['batch_number'] ?? ('BATCH-' . strtoupper(\Illuminate\Support\Str::random(6))),
                'expiration_date' => $validated['expiration_date'] ?? null,
                'quantity' => $validated['quantity_on_hand'],
                'purchase_price' => $validated['purchase_price'] ?? null,
                'storage_condition' => $validated['storage_condition'] ?? null,
                'supplier' => $validated['supplier'] ?? null,
            ]);
        }

        \App\Models\AuditLog::create([
            'pharmacy_id' => $pharmacy->id,
            'user_id' => Auth::id(),
            'action' => 'Added to Inventory',
            'details' => "Added medicine ID {$validated['medicine_id']} to inventory. Initial stock: {$validated['quantity_on_hand']}, Price: {$validated['selling_price']}",
        ]);

        if ($validated['quantity_on_hand'] > 0) {
            if(class_exists(\App\Models\StockAlert::class)) {
                \App\Models\StockAlert::checkAndNotify($pharmacy->id, $validated['medicine_id']);
            }
        }

        return redirect('/portal/inventory')->with('success', 'Medicine added to inventory!');
    }

    // Fetch batch details for a medicine
    public function batchDetails($medicineId)
    {
        $pharmacy = $this->getMyPharmacy();
        if (!$pharmacy) {
            return response()->json(['error' => 'Pharmacy not found.'], 404);
        }

        $batches = \App\Models\InventoryBatch::where('pharmacy_id', $pharmacy->id)
            ->where('medicine_id', $medicineId)
            ->orderBy('expiration_date', 'asc')
            ->get();

        return response()->json([
            'medicine_id' => (int) $medicineId,
            'batches' => $batches,
        ]);
    }

    // Add batch to existing medicine
    public function addBatch(Request $request)
    {
        $pharmacy = $this->getMyPharmacy();
        if (!$pharmacy) {
            return back()->with('error', 'Pharmacy not found.');
        }

        $validated = $request->validate([
            'medicine_id' => 'required|exists:medicines,id',
            'batch_number' => 'required|string|max:100',
            'expiration_date' => 'required|date',
            'quantity' => 'required|integer|min:1',
            'purchase_price' => 'nullable|numeric|min:0',
            'storage_condition' => 'nullable|string|max:255',
            'supplier' => 'nullable|string|max:255',
        ]);

        app(\App\Services\InventoryService::class)->addBatch($pharmacy->id, $validated['medicine_id'], $validated);

        return back()->with('success', 'New stock batch added successfully!');
    }

    // Toggle status of a batch (active / quarantined / expired / depleted)
    public function toggleBatchStatus(Request $request, $batchId)
    {
        $pharmacy = $this->getMyPharmacy();
        if (!$pharmacy) {
            return response()->json(['error' => 'Pharmacy not found.'], 404);
        }

        $validated = $request->validate([
            'status' => 'required|in:active,quarantined,expired,depleted',
        ]);

        $batch = \App\Models\InventoryBatch::where('id', $batchId)
            ->where('pharmacy_id', $pharmacy->id)
            ->firstOrFail();

        app(\App\Services\InventoryService::class)->toggleBatchStatus($batch->id, $validated['status']);

        return response()->json([
            'success' => true,
            'message' => "Batch status updated to {$validated['status']}.",
            'batch' => $batch->fresh(),
        ]);
    }

    // Lightweight operational summary metrics for pharmacy admin dashboard
    public function operationalSummary()
    {
        $pharmacy = $this->getMyPharmacy();
        if (!$pharmacy) {
            return response()->json(['error' => 'Pharmacy not found.'], 404);
        }

        $summary = app(\App\Services\InventoryService::class)->getOperationalSummary($pharmacy->id);

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }


    // Create a BRAND NEW medicine in the global database AND add it to this pharmacy's inventory
    public function storeNewMedicine(Request $request)
    {
        $pharmacy = $this->getMyPharmacy();

        $validated = $request->validate([
            // 1. Global Medicine Fields
            'generic_name' => 'required|string|max:255',
            'brand_name' => 'nullable|string|max:255',
            'drug_category' => 'nullable|string|max:100',
            'primary_use' => 'nullable|string', 
            'dosage_form' => 'nullable|string|max:100',
            'strength' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'prescription_required' => 'boolean',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048', 

            // 2. Local Inventory Fields (Specific to this pharmacy)
            'unit' => 'required|string', 
            'selling_price' => 'required|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'quantity_on_hand' => 'required|integer|min:0',
            'reorder_level' => 'nullable|integer|min:0',
            'batch_number' => 'nullable|string|max:100',
            'expiration_date' => 'nullable|date',
            'storage_condition' => 'nullable|string|max:255',
            'supplier' => 'nullable|string|max:255',
            'last_restocked_at' => 'nullable|date',
        ]);

        // Handle Image Upload if provided during new medicine creation
        $imagePath = null;
        if ($request->hasFile('image')) {
            $storageService = app(\App\Services\SupabaseStorageService::class);
            $imagePath = $storageService->upload($request->file('image'), \App\Services\SupabaseStorageService::BUCKET_MEDICINE_IMAGES);
        }

        // Step A: Create the global medicine record
        $nameForSlug = $validated['brand_name'] ?? $validated['generic_name'];
        
        $medicine = Medicine::create([
            'slug' => \Illuminate\Support\Str::slug($nameForSlug) . '-' . \Illuminate\Support\Str::random(5),
            'generic_name' => $validated['generic_name'],
            'brand_name' => $validated['brand_name'] ?? null,
            'drug_category' => $validated['drug_category'] ?? null,
            'primary_use' => $validated['primary_use'] ?? null,
            'dosage_form' => $validated['dosage_form'] ?? null,
            'strength' => $validated['strength'] ?? null,
            'description' => $validated['description'] ?? null,
            'prescription_required' => $request->boolean('prescription_required'),
            'image' => $imagePath, // Save the image path
        ]);

        // Step B: Attach it to this specific pharmacy
        $pharmacy->medicines()->attach($medicine->id, [
            'unit' => $validated['unit'], 
            'selling_price' => $validated['selling_price'],
            'purchase_price' => $validated['purchase_price'] ?? null,
            'quantity_on_hand' => $validated['quantity_on_hand'],
            'reorder_level' => $validated['reorder_level'] ?? 10,
            'batch_number' => $validated['batch_number'] ?? null,
            'expiration_date' => $validated['expiration_date'] ?? null,
            'storage_condition' => $validated['storage_condition'] ?? null,
            'supplier' => $validated['supplier'] ?? null,
            'last_restocked_at' => $validated['last_restocked_at'] ?? null,
            'is_available' => $validated['quantity_on_hand'] > 0,
        ]);

        if ($validated['quantity_on_hand'] > 0 || !empty($validated['batch_number'])) {
            app(\App\Services\InventoryService::class)->addBatch($pharmacy->id, $medicine->id, [
                'batch_number' => $validated['batch_number'] ?? ('BATCH-' . strtoupper(\Illuminate\Support\Str::random(6))),
                'expiration_date' => $validated['expiration_date'] ?? null,
                'quantity' => $validated['quantity_on_hand'],
                'purchase_price' => $validated['purchase_price'] ?? null,
                'storage_condition' => $validated['storage_condition'] ?? null,
                'supplier' => $validated['supplier'] ?? null,
            ]);
        }

        \App\Models\AuditLog::create([
            'pharmacy_id' => $pharmacy->id,
            'user_id' => Auth::id(),
            'action' => 'Created New Medicine',
            'details' => "Created new medicine '{$validated['generic_name']}' and added to inventory. Stock: {$validated['quantity_on_hand']}",
        ]);

        return redirect('/portal/inventory')->with('success', 'New medicine created and added to your stock!');
    }

    // Update inventory item
    public function updateInventoryItem(Request $request, $medicineId)
    {
        $pharmacy = $this->getMyPharmacy();
        
        $validated = $request->validate([
            'unit' => 'required|string', 
            'selling_price' => 'required|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'quantity_on_hand' => 'required|integer|min:0',
            'reorder_level' => 'nullable|integer|min:0',
            'batch_number' => 'nullable|string|max:100',
            'expiration_date' => 'nullable|date',
            'storage_condition' => 'nullable|string|max:255',
            'supplier' => 'nullable|string|max:255',
            'last_restocked_at' => 'nullable|date',
        ]);

        // Get old stock to check if it was out of stock
        $oldStock = $pharmacy->medicines()->where('medicines.id', $medicineId)->first()?->pivot->quantity_on_hand ?? 0;

        $pharmacy->medicines()->updateExistingPivot($medicineId, [
            'unit' => $validated['unit'], 
            'selling_price' => $validated['selling_price'],
            'purchase_price' => $validated['purchase_price'] ?? null,
            'quantity_on_hand' => $validated['quantity_on_hand'],
            'reorder_level' => $validated['reorder_level'] ?? 10,
            'batch_number' => $validated['batch_number'] ?? null,
            'expiration_date' => $validated['expiration_date'] ?? null,
            'storage_condition' => $validated['storage_condition'] ?? null,
            'supplier' => $validated['supplier'] ?? null,
            'last_restocked_at' => $validated['last_restocked_at'] ?? null,
            'is_available' => $validated['quantity_on_hand'] > 0,
        ]);

        $medicineName = \App\Models\Medicine::find($medicineId)->generic_name;
        \App\Models\AuditLog::create([
            'pharmacy_id' => $pharmacy->id,
            'user_id' => Auth::id(),
            'action' => 'Updated Inventory Item',
            'details' => "Updated '{$medicineName}'. Old stock: {$oldStock}, New stock: {$validated['quantity_on_hand']}. Price: {$validated['selling_price']}",
        ]);

        // ==============================================================
        // ✅ THE FIX: SEND THE RESTOCK NOTIFICATION TO ALL SUBSCRIBED USERS
        // ==============================================================
        if ($oldStock == 0 && $validated['quantity_on_hand'] > 0) {
            \App\Models\StockAlert::checkAndNotify($pharmacy->id, $medicineId);
        }

        return redirect('/portal/inventory')->with('success', 'Inventory updated!');
    }

    // Remove from inventory
    public function removeFromInventory($medicineId)
    {
        $pharmacy = $this->getMyPharmacy();
        $pharmacy->medicines()->detach($medicineId);

        return redirect('/portal/inventory')->with('success', 'Medicine removed from inventory.');
    }

    // =============================================
    // ADMIN CATALOG MANAGEMENT
    // =============================================
    
    // Show the Admin Catalog Page
    public function catalog()
    {
        $pharmacy = $this->getMyPharmacy();
        
        // Get all medicines this specific pharmacy sells
        $medicines = $pharmacy->medicines()->get(); 
        
        return view('pharmacy.catalog', compact('medicines'));
    }

    // Handle Image Uploads and Description Updates
    public function updateCatalog(Request $request, $id)
    {
        $request->validate([
            'description' => 'nullable|string|max:1000',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048' // Max 2MB image
        ]);

        $medicine = \App\Models\Medicine::findOrFail($id);
        
        // If the admin uploaded a new image, save it securely via Supabase Storage
        if ($request->hasFile('image')) {
            $storageService = app(\App\Services\SupabaseStorageService::class);
            if ($medicine->image) {
                $storageService->delete(\App\Services\SupabaseStorageService::BUCKET_MEDICINE_IMAGES, $medicine->image);
            }
            $path = $storageService->upload($request->file('image'), \App\Services\SupabaseStorageService::BUCKET_MEDICINE_IMAGES);
            $medicine->image = $path;
        }

        // Update description
        if ($request->has('description')) {
            $medicine->description = $request->description;
        }
        
        $medicine->save();

        return back()->with('success', $medicine->generic_name . ' catalog details updated successfully!');
    }

    // =============================================
    // PROFILE MANAGEMENT
    // =============================================

    public function profile()
    {
        $pharmacy = $this->getMyPharmacy();
        return view('pharmacy.profile', compact('pharmacy'));
    }

    public function updateProfile(Request $request)
    {
        $pharmacy = $this->getMyPharmacy();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'owner_name' => 'nullable|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'operating_hours_open' => 'nullable|string',
            'operating_hours_close' => 'nullable|string',
        ]);

        if (!\App\Services\GeofenceService::isInsideAlijis((float)$validated['latitude'], (float)$validated['longitude'])) {
            return back()->withErrors([
                'latitude' => 'The specified coordinates are outside the Barangay Alijis boundary radius. Only locations within Barangay Alijis are allowed.'
            ])->withInput();
        }

        $pharmacy->update([
            'name' => $validated['name'],
            'address' => $validated['address'],
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'owner_name' => $validated['owner_name'] ?? null,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'operating_hours' => [
                'open' => $validated['operating_hours_open'] ?? '8:00 AM',
                'close' => $validated['operating_hours_close'] ?? '9:00 PM',
            ],
        ]);

        return redirect('/portal/profile')->with('success', 'Profile updated!');
    }

    // =============================================
    // SETTINGS & LOGO MANAGEMENT
    // =============================================

    public function settings()
    {
        $pharmacy = $this->getMyPharmacy();
        return view('pharmacy.settings', compact('pharmacy'));
    }

    public function updateSettings(Request $request)
    {
        $pharmacy = $this->getMyPharmacy();

        // Validate the incoming settings
        $validated = $request->validate([
            'theme_color' => 'nullable|string|max:7',
            'is_active' => 'nullable', 
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,svg,webp|max:2048', // Max 2MB
            'remove_logo' => 'nullable|boolean', 
            'cover_photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048', // Max 2MB
            'remove_cover_photo' => 'nullable|boolean', 
        ]);

        $dataToUpdate = [
            'theme_color' => $validated['theme_color'] ?? $pharmacy->theme_color,
            'is_active' => $request->has('is_active'), 
        ];

        $storageService = app(\App\Services\SupabaseStorageService::class);

        // HANDLE LOGO REMOVAL
        if ($request->boolean('remove_logo')) {
            if ($pharmacy->logo) {
                $storageService->delete(\App\Services\SupabaseStorageService::BUCKET_PHARMACY_LOGOS, $pharmacy->logo);
            }
            $dataToUpdate['logo'] = null; // Clear from database
        }

        // Handle New Logo Upload
        if ($request->hasFile('logo')) {
            if ($pharmacy->logo) {
                $storageService->delete(\App\Services\SupabaseStorageService::BUCKET_PHARMACY_LOGOS, $pharmacy->logo);
            }
            $path = $storageService->upload($request->file('logo'), \App\Services\SupabaseStorageService::BUCKET_PHARMACY_LOGOS);
            $dataToUpdate['logo'] = $path;
        }

        // HANDLE COVER PHOTO REMOVAL
        if ($request->boolean('remove_cover_photo')) {
            if ($pharmacy->cover_photo) {
                $storageService->delete(\App\Services\SupabaseStorageService::BUCKET_PHARMACY_COVERS, $pharmacy->cover_photo);
            }
            $dataToUpdate['cover_photo'] = null; // Clear from database
        }

        // Handle New Cover Photo Upload
        if ($request->hasFile('cover_photo')) {
            if ($pharmacy->cover_photo) {
                $storageService->delete(\App\Services\SupabaseStorageService::BUCKET_PHARMACY_COVERS, $pharmacy->cover_photo);
            }
            $path = $storageService->upload($request->file('cover_photo'), \App\Services\SupabaseStorageService::BUCKET_PHARMACY_COVERS);
            $dataToUpdate['cover_photo'] = $path;
        }

        $pharmacy->update($dataToUpdate);

        return redirect()->route('pharmacy.settings')->with('success', 'Pharmacy settings and branding updated!');
    }



    // =============================================
    // INCOMING ORDERS (from Customer checkout)
    // =============================================
    public function orders(Request $request)
    {
        $pharmacy = $this->getMyPharmacy();
        if (!$pharmacy) {
            return redirect('/login')->with('error', 'No pharmacy associated with your account.');
        }

        $filter = $request->get('status', 'all');

        $query = \App\Models\Order::where('pharmacy_id', $pharmacy->id)
            ->with(['user', 'items.medicine', 'delivery.driver.driverProfile', 'deliveryPartner.driverProfile'])
            ->orderByDesc('created_at');

        if ($filter !== 'all') {
            $query->where('status', $filter);
        }

        $orders = $query->get();

        $counts = [
            'all' => \App\Models\Order::where('pharmacy_id', $pharmacy->id)->count(),
            'pending_confirmation' => \App\Models\Order::where('pharmacy_id', $pharmacy->id)->where('status', \App\Models\Order::STATUS_PENDING_CONFIRMATION)->count(),
            'pending' => \App\Models\Order::where('pharmacy_id', $pharmacy->id)->where('status', \App\Models\Order::STATUS_PENDING)->count(),
            'accepted' => \App\Models\Order::where('pharmacy_id', $pharmacy->id)->where('status', \App\Models\Order::STATUS_ACCEPTED)->count(),
            'picked_up' => \App\Models\Order::where('pharmacy_id', $pharmacy->id)->whereIn('status', [\App\Models\Order::STATUS_AT_PHARMACY, \App\Models\Order::STATUS_PICKED_UP])->count(),
            'delivered' => \App\Models\Order::where('pharmacy_id', $pharmacy->id)->where('status', \App\Models\Order::STATUS_DELIVERED)->count(),
        ];

        return view('pharmacy.orders', compact('pharmacy', 'orders', 'counts', 'filter'));
    }

    public function confirmOrder(Request $request, \App\Services\InventoryService $inventoryService, \App\Services\MaximDeliveryService $maximService, \App\Services\DeliveryService $deliveryService, $id)
    {
        $pharmacy = $this->getMyPharmacy();
        $order = \App\Models\Order::where('id', $id)->where('pharmacy_id', $pharmacy->id)->with('items.medicine', 'user', 'pharmacy')->firstOrFail();

        if ($order->status !== \App\Models\Order::STATUS_PENDING_CONFIRMATION) {
            return back()->with('error', 'Order cannot be confirmed at this stage.');
        }

        // Deduct inventory using FIFO
        $allDeductions = [];
        foreach ($order->items as $item) {
            $deductions = $inventoryService->deductFIFO($pharmacy->id, $item->medicine_id, $item->quantity);
            $allDeductions[$item->medicine_id] = $deductions;
        }

        $order->update([
            'status' => \App\Models\Order::STATUS_PENDING, // Now waiting for rider
            'pharmacy_confirmed' => true,
            'confirmed_at' => now(),
            'fifo_deductions' => $allDeductions
        ]);

        // Create official GEORX Delivery Booking
        try {
            $deliveryService->createDeliveryForOrder($order);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to create delivery booking for order #{$order->id}: " . $e->getMessage());
        }

        // Send to Maxim API
        $booking = $maximService->createBooking($order);

        if ($booking['success']) {
            $order->update([
                'maxim_booking_id' => $booking['booking_id'],
                'maxim_tracking_url' => $booking['tracking_url'] ?? null,
            ]);
        }

        // Notify user
        $order->user->notify(new \App\Notifications\OrderStatusChanged($order, 'Your order has been confirmed by the pharmacy and a rider is being assigned.', 'fas fa-check-circle text-emerald-500'));

        return back()->with('success', 'Order #' . $order->id . ' confirmed! Rider delivery booking is now available.');
    }

    public function rejectOrder(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        $pharmacy = $this->getMyPharmacy();
        $order = \App\Models\Order::where('id', $id)->where('pharmacy_id', $pharmacy->id)->firstOrFail();

        if ($order->status !== \App\Models\Order::STATUS_PENDING_CONFIRMATION) {
            return back()->with('error', 'Order cannot be rejected at this stage.');
        }

        $order->update([
            'status' => \App\Models\Order::STATUS_REJECTED,
            'rejected_at' => now(),
            'rejection_reason' => $request->reason
        ]);

        // Notify user
        $order->user->notify(new \App\Notifications\OrderStatusChanged($order, 'Your order was rejected: ' . $request->reason, 'fas fa-times-circle text-rose-500'));

        return back()->with('success', 'Order #' . $order->id . ' has been rejected.');
    }

    public function prepareOrder($id)
    {
        $pharmacy = $this->getMyPharmacy();
        $order = \App\Models\Order::where('id', $id)->where('pharmacy_id', $pharmacy->id)->firstOrFail();

        $order->update(['is_prepared' => true]);
        
        $order->user->notify(new \App\Notifications\OrderStatusChanged($order, 'Your order is prepared and ready for pickup!', 'fas fa-box text-blue-500'));

        return back()->with('success', 'Order #' . $order->id . ' marked as prepared and ready for pickup.');
    }
}