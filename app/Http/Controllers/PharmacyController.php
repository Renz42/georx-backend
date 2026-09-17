<?php

namespace App\Http\Controllers;

use App\Models\Pharmacy;
use App\Models\Medicine;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PharmacyController extends Controller
{
    /**
     * Show admin page to manage pharmacies
     */
    public function index()
    {
        $pharmacies = Pharmacy::with('medicines')->orderBy('name')->get();
        return view('admin.index', compact('pharmacies'));
    }

    /**
     * Show form to create a new pharmacy
     */
    public function create()
    {
        $medicines = Medicine::orderBy('name')->get();
        return view('admin.create', compact('medicines'));
    }

    /**
     * Store a new pharmacy
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'contact_number' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'owner_name' => 'nullable|string|max:255',
            'operating_hours_open' => 'nullable|string',
            'operating_hours_close' => 'nullable|string',
        ]);

        $pharmacy = Pharmacy::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']) . '-' . uniqid(),
            'address' => $validated['address'],
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'phone' => $validated['contact_number'] ?? null,
            'email' => $validated['email'] ?? null,
            'owner_name' => $validated['owner_name'] ?? null,
            'operating_hours' => [
                'open' => $validated['operating_hours_open'] ?? '8:00 AM',
                'close' => $validated['operating_hours_close'] ?? '9:00 PM',
            ],
            'is_active' => true,
        ]);

        return redirect()->route('admin.pharmacies.index')
            ->with('success', 'Pharmacy "' . $pharmacy->name . '" added successfully!');
    }

    /**
     * Show form to edit a pharmacy
     */
    public function edit(Pharmacy $pharmacy)
    {
        $medicines = Medicine::orderBy('name')->get();
        $pharmacy->load('medicines');
        return view('admin.edit', compact('pharmacy', 'medicines'));
    }

    /**
     * Update a pharmacy
     */
    public function update(Request $request, Pharmacy $pharmacy)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'contact_number' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'owner_name' => 'nullable|string|max:255',
            'operating_hours_open' => 'nullable|string',
            'operating_hours_close' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $pharmacy->update([
            'name' => $validated['name'],
            'address' => $validated['address'],
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'phone' => $validated['contact_number'] ?? null,
            'email' => $validated['email'] ?? null,
            'owner_name' => $validated['owner_name'] ?? null,
            'operating_hours' => [
                'open' => $validated['operating_hours_open'] ?? '8:00 AM',
                'close' => $validated['operating_hours_close'] ?? '9:00 PM',
            ],
            'is_active' => $request->has('is_active'),
        ]);

        return redirect()->route('admin.pharmacies.index')
            ->with('success', 'Pharmacy "' . $pharmacy->name . '" updated successfully!');
    }

    /**
     * Delete a pharmacy
     */
    public function destroy(Pharmacy $pharmacy)
    {
        $name = $pharmacy->name;
        $pharmacy->medicines()->detach();
        $pharmacy->delete();

        return redirect()->route('admin.pharmacies.index')
            ->with('success', 'Pharmacy "' . $name . '" deleted successfully!');
    }

    /**
     * Manage inventory for a pharmacy
     */
    public function inventory(Pharmacy $pharmacy)
    {
        $medicines = Medicine::orderBy('name')->get();
        $pharmacy->load('medicines');
        return view('admin.inventory', compact('pharmacy', 'medicines'));
    }

    /**
     * Update inventory for a pharmacy
     */
    public function updateInventory(Request $request, Pharmacy $pharmacy)
    {
        $validated = $request->validate([
            'medicines' => 'nullable|array',
            'medicines.*.medicine_id' => 'required|exists:medicines,id',
            'medicines.*.price' => 'nullable|numeric|min:0',
            'medicines.*.stock_quantity' => 'nullable|integer|min:0',
        ]);

        // Detach all existing medicines
        $pharmacy->medicines()->detach();

        // Attach new medicines with pivot data (only those with price and stock > 0)
        if (!empty($validated['medicines'])) {
            foreach ($validated['medicines'] as $item) {
                $price = $item['price'] ?? 0;
                $quantity = $item['stock_quantity'] ?? 0;
                
                // Only add if both price and quantity are set and quantity > 0
                if ($price > 0 && $quantity > 0) {
                    $pharmacy->medicines()->attach($item['medicine_id'], [
                        'price' => $price,
                        'stock_quantity' => $quantity,
                        'is_available' => true,
                    ]);
                }
            }
        }

        return redirect()->route('admin.pharmacies.inventory', $pharmacy)
            ->with('success', 'Inventory updated successfully!');
    }

    /**
     * Store a new medicine
     */
    public function storeMedicine(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'generic_name' => 'nullable|string|max:255',
            'category' => 'required|string|max:100',
            'description' => 'nullable|string|max:1000',
            'dosage_form' => 'nullable|string|max:100',
            'strength' => 'nullable|string|max:100',
            'requires_prescription' => 'boolean',
        ]);

        $medicine = Medicine::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']) . '-' . uniqid(),
            'generic_name' => $validated['generic_name'] ?? null,
            'category' => $validated['category'],
            'description' => $validated['description'] ?? null,
            'form' => $validated['dosage_form'] ?? null,
            'dosage' => $validated['strength'] ?? null,
            'requires_prescription' => $request->has('requires_prescription'),
        ]);

        return redirect()->back()
            ->with('success', 'Medicine "' . $medicine->name . '" added successfully!');
    }

    // ==========================================
    // NEW: Public Pharmacy Storefront Catalog
    // ==========================================
    public function publicCatalog($id)
    {
        $pharmacy = Pharmacy::with(['medicines' => function($query) {
            // Show all medicines associated with this pharmacy, including out of stock
            // Ordered by stock (in stock first, out of stock last)
            $query->orderByPivot('quantity_on_hand', 'desc');
        }])->findOrFail($id);

        return view('public.pharmacy_catalog', compact('pharmacy'));
    }

    // ==========================================
    // NEW: Public Pharmacy Profile Page
    // ==========================================
    public function publicProfile($id)
    {
        $pharmacy = Pharmacy::with(['medicines' => function($query) {
            $query->wherePivot('quantity_on_hand', '>', 0)
                  ->wherePivot('is_available', true);
        }])->findOrFail($id);

        // Get aggregate ratings
        $ratings = \App\Models\Review::aggregateForPharmacy($id);

        // Get latest reviews
        $reviews = \App\Models\Review::where('pharmacy_id', $id)
            ->with('user')
            ->latest()
            ->take(10)
            ->get();

        // Check if pharmacy is currently open
        $isOpen = false;
        if ($pharmacy->operating_hours) {
            $now = now()->format('H:i');
            $open = $pharmacy->operating_hours['open'] ?? null;
            $close = $pharmacy->operating_hours['close'] ?? null;
            if ($open && $close) {
                try {
                    $openTime = \Carbon\Carbon::parse($open)->format('H:i');
                    $closeTime = \Carbon\Carbon::parse($close)->format('H:i');
                    $isOpen = $now >= $openTime && $now <= $closeTime;
                } catch (\Exception $e) {
                    $isOpen = false;
                }
            }
        }

        return view('public.pharmacy_profile', compact('pharmacy', 'ratings', 'reviews', 'isOpen'));
    }

    // ==========================================
    // NEW: Public Medicine Details Page
    // ==========================================
    public function publicMedicineDetails($pharmacy_id, $medicine_id)
    {
        $pharmacy = Pharmacy::findOrFail($pharmacy_id);
        
        $medicine = $pharmacy->medicines()->where('medicine_id', $medicine_id)->firstOrFail();

        return view('public.medicine_details', compact('pharmacy', 'medicine'));
    }
}