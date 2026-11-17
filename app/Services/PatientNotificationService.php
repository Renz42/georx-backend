<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PatientNotificationService
{
    public function __construct(
        protected ExpoPushService $expoPushService
    ) {
    }

    /**
     * Dispatch an idempotent database + optional push notification to a user for an order event.
     */
    public function sendToOrderRecipient(
        Order $order,
        ?User $recipient,
        string $eventKey,
        Notification|string $notificationOrType,
        ?array $overrideData = null,
        bool $sendPush = true,
        string $channelId = 'orders'
    ): bool {
        if (!$recipient || !$recipient->id) {
            return false;
        }

        if ($notificationOrType instanceof Notification) {
            $type = get_class($notificationOrType);
            $data = $overrideData ?? $notificationOrType->toArray($recipient);
        } else {
            $type = $notificationOrType;
            $data = $overrideData ?? [];
        }

        $data['order_id'] = (int) $order->id;
        $data['event_key'] = $eventKey;
        $data['action_url'] = $data['action_url'] ?? "/orders/{$order->id}";
        $data['url'] = $data['url'] ?? route('orders.show', $order->id);
        $data['title'] = $data['title'] ?? ('Order Update #' . $order->id);

        if ($this->hasEventNotification($recipient, $eventKey)) {
            return false;
        }

        try {
            $recipient->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => $type,
                'data' => $data,
                'read_at' => null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to persist order notification', [
                'order_id' => $order->id,
                'recipient_id' => $recipient->id,
                'event_key' => $eventKey,
                'error' => $e->getMessage(),
            ]);
            return false;
        }

        if ($sendPush) {
            $this->expoPushService->sendToUser(
                (int) $recipient->id,
                (string) $data['title'],
                (string) ($data['message'] ?? 'You have a new order update.'),
                $data,
                $channelId,
                'high'
            );
        }

        return true;
    }

    public function notifyOrderCustomer(
        Order $order,
        string $eventSuffix,
        Notification|string $notificationOrType,
        ?array $overrideData = null,
        bool $sendPush = true
    ): bool {
        $order->loadMissing('user', 'pharmacy');

        return $this->sendToOrderRecipient(
            $order,
            $order->user,
            "order:{$order->id}:{$eventSuffix}",
            $notificationOrType,
            $overrideData,
            $sendPush,
            'orders'
        );
    }

    public function notifyOrderPharmacy(
        Order $order,
        string $eventSuffix,
        Notification|string $notificationOrType,
        ?array $overrideData = null,
        bool $sendPush = false
    ): int {
        $order->loadMissing('pharmacy');

        $staff = User::where('pharmacy_id', $order->pharmacy_id)
            ->whereIn('role', [
                User::ROLE_PHARMACY_OWNER,
                User::ROLE_PHARMACIST,
                User::ROLE_PHARMACY_STAFF,
            ])
            ->get();

        if ($staff->isEmpty()) {
            $fallback = User::where('pharmacy_id', $order->pharmacy_id)->first();
            if ($fallback) {
                $staff = collect([$fallback]);
            }
        }

        $count = 0;
        foreach ($staff as $recipient) {
            $sent = $this->sendToOrderRecipient(
                $order,
                $recipient,
                "pharmacy_order:{$order->id}:{$eventSuffix}",
                $notificationOrType,
                $overrideData,
                $sendPush,
                'orders'
            );
            if ($sent) {
                $count++;
            }
        }

        return $count;
    }

    private function hasEventNotification(User $recipient, string $eventKey): bool
    {
        $rows = DB::table('notifications')
            ->where('notifiable_type', $recipient->getMorphClass())
            ->where('notifiable_id', $recipient->id)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get(['data']);

        foreach ($rows as $row) {
            $decoded = is_array($row->data)
                ? $row->data
                : json_decode((string) $row->data, true);

            if (is_array($decoded) && ($decoded['event_key'] ?? null) === $eventKey) {
                return true;
            }
        }

        return false;
    }
}
