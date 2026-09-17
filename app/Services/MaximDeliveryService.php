<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MaximDeliveryService
{
    protected $apiUrl;
    protected $apiKey;
    protected $isSandbox;

    public function __construct()
    {
        $this->apiUrl = config('services.maxim.api_url', 'https://api.maxim.com');
        $this->apiKey = config('services.maxim.api_key', 'test_key');
        $this->isSandbox = config('services.maxim.sandbox', true);
    }

    /**
     * Create a delivery booking with Maxim
     */
    public function createBooking(Order $order): array
    {
        $pharmacy = $order->pharmacy;
        $user = $order->user;

        $payload = [
            'pickup' => [
                'latitude'     => (float) $pharmacy->latitude,
                'longitude'    => (float) $pharmacy->longitude,
                'contact_name' => $pharmacy->name,
                'contact_phone'=> $pharmacy->phone,
                'address'      => $pharmacy->address,
                'notes'        => 'Pharmacy pickup — medicines ready for collection',
            ],
            'dropoff' => [
                'latitude'     => (float) $order->latitude,
                'longitude'    => (float) $order->longitude,
                'contact_name' => $user->name,
                'contact_phone'=> $user->phone,
                'address'      => $order->delivery_address,
            ],
            'package' => [
                'description'   => 'Medicines from GEORX Hub',
                'weight_kg'     => 1.0,
                'category'      => 'healthcare',
            ],
            'delivery_fee'  => (float) $order->delivery_fee,
            'currency'      => 'PHP',
            'reference_id'  => 'GEORX-' . $order->id,
            'callback_url'  => route('api.delivery.webhook', $order->id),
        ];

        if ($this->isSandbox) {
            Log::info('MAXIM SANDBOX: Create Booking Payload', $payload);
            
            return [
                'success' => true,
                'booking_id' => 'MAXIM-TEST-' . strtoupper(uniqid()),
                'tracking_url' => 'https://maxim.com/track/test-' . uniqid(),
                'estimated_arrival' => now()->addMinutes(30)->toIso8601String(),
            ];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ])->post($this->apiUrl . '/v1/bookings', $payload);

            if ($response->successful()) {
                return array_merge(['success' => true], $response->json());
            }

            Log::error('Maxim API Error', ['status' => $response->status(), 'body' => $response->body()]);
            return ['success' => false, 'error' => $response->json('message') ?? 'Unknown error'];

        } catch (\Exception $e) {
            Log::error('Maxim API Exception: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Cancel a booking
     */
    public function cancelBooking(string $bookingId): array
    {
        if ($this->isSandbox) {
            Log::info("MAXIM SANDBOX: Cancel Booking {$bookingId}");
            return ['success' => true];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->post($this->apiUrl . '/v1/bookings/' . $bookingId . '/cancel');

            return ['success' => $response->successful()];
        } catch (\Exception $e) {
            Log::error('Maxim API Cancel Exception: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Calculate delivery fee based on distance
     * For now, returns flat 50 PHP, but prepared for API estimate
     */
    public function estimateFee(float $pickupLat, float $pickupLng, float $dropoffLat, float $dropoffLng): float
    {
        // To be replaced with API call
        return 50.00;
    }
}
