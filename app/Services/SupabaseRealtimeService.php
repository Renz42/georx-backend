<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class SupabaseRealtimeService
{
    // Realtime Channel Names
    public const CHANNEL_DELIVERIES  = 'realtime:deliveries';
    public const CHANNEL_DRIVER_GPS  = 'realtime:driver_gps';
    public const CHANNEL_NOTIFICATIONS = 'realtime:notifications';

    /**
     * Payload structure for Supabase Realtime broadcast events.
     * Consumed identically by both Laravel Web Client & Mobile App (React Native).
     *
     * @param string $event
     * @param Delivery $delivery
     * @return array
     */
    public static function buildDeliveryEventPayload(string $event, Delivery $delivery): array
    {
        return [
            'event'       => $event,
            'timestamp'   => now()->toIso8601String(),
            'delivery_id' => $delivery->id,
            'order_id'    => $delivery->order_id,
            'customer_id' => $delivery->customer_id,
            'driver_id'   => $delivery->driver_id,
            'status'      => $delivery->status,
            'pickup'      => [
                'pharmacy_id' => $delivery->pickup_pharmacy_id,
                'name'        => $delivery->pickupPharmacy->name ?? null,
                'latitude'    => $delivery->pickup_latitude,
                'longitude'   => $delivery->pickup_longitude,
            ],
            'destination' => [
                'address'   => $delivery->delivery_address,
                'latitude'  => $delivery->delivery_latitude,
                'longitude' => $delivery->delivery_longitude,
            ],
            'timestamps'  => [
                'accepted_at'         => $delivery->driver_accepted_at?->toIso8601String(),
                'at_pharmacy_at'      => $delivery->driver_at_pharmacy_at?->toIso8601String(),
                'picked_up_at'        => $delivery->picked_up_at?->toIso8601String(),
                'out_for_delivery_at' => $delivery->out_for_delivery_at?->toIso8601String(),
                'delivered_at'        => $delivery->delivered_at?->toIso8601String(),
            ]
        ];
    }

    /**
     * Broadcast delivery status change.
     */
    public static function broadcastDeliveryStatusChange(Delivery $delivery, string $newStatus, array $extra = []): array
    {
        $payload = self::buildDeliveryEventPayload('delivery_status_changed', $delivery);
        $payload['extra'] = $extra;
        Log::info("Supabase Realtime Broadcast: delivery #{$delivery->id} status changed to {$newStatus}", $payload);
        return $payload;
    }

    /**
     * Payload structure for driver GPS live tracking broadcasts.
     */
    public static function buildDriverLocationPayload($driverProfile, ?Delivery $activeDelivery = null): array
    {
        return [
            'event'             => 'driver_location_updated',
            'timestamp'         => now()->toIso8601String(),
            'driver_id'         => $driverProfile->user_id,
            'current_latitude'  => (float) $driverProfile->current_latitude,
            'current_longitude' => (float) $driverProfile->current_longitude,
            'last_location_at'  => $driverProfile->last_location_at?->toIso8601String(),
            'active_delivery_id' => $activeDelivery?->id,
            'active_order_id'    => $activeDelivery?->order_id,
        ];
    }
}
