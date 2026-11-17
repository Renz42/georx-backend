<?php

namespace App\Services;

use App\Models\UserDeviceToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExpoPushService
{
    public function isValidExpoToken(?string $token): bool
    {
        $token = trim((string) $token);
        if ($token === '') {
            return false;
        }

        return (bool) preg_match('/^(ExponentPushToken|ExpoPushToken)\[[^\]]+\]$/', $token);
    }

    /**
     * Send push notifications to registered devices for the given user IDs.
     * Failures are logged and never thrown so DB notification records stay intact.
     *
     * @param  array<int, int|string>  $userIds
     * @return array{attempted:int,sent:int,pruned:int}
     */
    public function sendToUserIds(
        array $userIds,
        string $title,
        string $body,
        array $data = [],
        ?string $channelId = null,
        string $priority = 'high'
    ): array {
        $cleanIds = array_values(array_unique(array_filter(array_map('intval', $userIds), fn (int $id) => $id > 0)));
        if (empty($cleanIds) || !config('services.notifications.expo_push_enabled', true)) {
            return ['attempted' => 0, 'sent' => 0, 'pruned' => 0];
        }

        $tokens = UserDeviceToken::query()
            ->whereIn('user_id', $cleanIds)
            ->pluck('device_token')
            ->map(fn ($t) => trim((string) $t))
            ->filter(fn (string $t) => $this->isValidExpoToken($t))
            ->unique()
            ->values()
            ->all();

        if (empty($tokens)) {
            return ['attempted' => 0, 'sent' => 0, 'pruned' => 0];
        }

        $sanitizedData = $this->sanitizePayloadData($data);
        $url = (string) config('services.notifications.expo_push_url', 'https://exp.host/--/api/v2/push/send');
        $timeout = max(2, (int) config('services.notifications.expo_push_timeout', 5));

        $sent = 0;
        $prunedTokens = [];

        foreach (array_chunk($tokens, 100) as $tokenChunk) {
            $messages = [];
            foreach ($tokenChunk as $token) {
                $payload = [
                    'to' => $token,
                    'sound' => 'default',
                    'title' => $title,
                    'body' => $body,
                    'data' => $sanitizedData,
                    'priority' => $priority,
                ];
                if ($channelId) {
                    $payload['channelId'] = $channelId;
                }
                $messages[] = $payload;
            }

            $response = null;
            $attempts = 0;
            $maxAttempts = 2;

            while ($attempts < $maxAttempts) {
                $attempts++;
                try {
                    $response = Http::acceptJson()
                        ->contentType('application/json')
                        ->connectTimeout(min(3, $timeout))
                        ->timeout($timeout)
                        ->post($url, $messages);

                    if ($response->successful()) {
                        break;
                    }

                    $status = $response->status();
                    if ($attempts < $maxAttempts && ($status === 429 || $status >= 500)) {
                        usleep(150000);
                        continue;
                    }

                    Log::warning('Expo push request returned non-2xx status', [
                        'status' => $status,
                        'attempts' => $attempts,
                        'token_count' => count($tokenChunk),
                        'user_count' => count($cleanIds),
                    ]);
                    break;
                } catch (\Throwable $e) {
                    if ($attempts < $maxAttempts) {
                        usleep(150000);
                        continue;
                    }
                    Log::warning('Expo push delivery failed safely', [
                        'error' => $e->getMessage(),
                        'attempts' => $attempts,
                        'token_count' => count($tokenChunk),
                        'user_count' => count($cleanIds),
                    ]);
                    $response = null;
                }
            }

            if (!$response || !$response->successful()) {
                continue;
            }

            try {
                $tickets = $response->json('data');
                if (!is_array($tickets)) {
                    $sent += count($tokenChunk);
                    continue;
                }

                foreach ($tickets as $idx => $ticket) {
                    if (!is_array($ticket)) {
                        continue;
                    }
                    if (($ticket['status'] ?? null) === 'ok') {
                        $sent++;
                        continue;
                    }
                    $errCode = $ticket['details']['error'] ?? null;
                    if ($errCode === 'DeviceNotRegistered' && isset($tokenChunk[$idx])) {
                        $prunedTokens[] = $tokenChunk[$idx];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Expo push ticket parsing warning', [
                    'error' => $e->getMessage(),
                    'token_count' => count($tokenChunk),
                ]);
            }
        }

        $prunedCount = 0;
        if (!empty($prunedTokens)) {
            try {
                $prunedCount = UserDeviceToken::query()
                    ->whereIn('device_token', array_values(array_unique($prunedTokens)))
                    ->delete();
            } catch (\Throwable $e) {
                Log::warning('Failed to prune unregistered device tokens', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'attempted' => count($tokens),
            'sent' => $sent,
            'pruned' => $prunedCount,
        ];
    }

    public function sendToUser(
        int $userId,
        string $title,
        string $body,
        array $data = [],
        ?string $channelId = null,
        string $priority = 'high'
    ): array {
        return $this->sendToUserIds([$userId], $title, $body, $data, $channelId, $priority);
    }

    /**
     * Fetch Expo push delivery receipts for ticket IDs and prune unregistered devices.
     *
     * @param  array<int, string>  $ticketIds
     * @return array<string, mixed>
     */
    public function checkReceipts(array $ticketIds): array
    {
        $cleanIds = array_values(array_unique(array_filter(array_map('trim', $ticketIds), fn (string $id) => $id !== '')));
        if (empty($cleanIds) || !config('services.notifications.expo_push_enabled', true)) {
            return [];
        }

        $timeout = max(2, (int) config('services.notifications.expo_push_timeout', 5));
        $receiptsUrl = preg_replace('#/send$#', '/getReceipts', (string) config('services.notifications.expo_push_url', 'https://exp.host/--/api/v2/push/send'));
        $collected = [];

        foreach (array_chunk($cleanIds, 100) as $idChunk) {
            try {
                $response = Http::acceptJson()
                    ->contentType('application/json')
                    ->connectTimeout(min(3, $timeout))
                    ->timeout($timeout)
                    ->post($receiptsUrl, ['ids' => $idChunk]);

                if ($response->successful() && is_array($response->json('data'))) {
                    foreach ($response->json('data') as $ticketId => $receipt) {
                        $collected[$ticketId] = $receipt;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Expo push receipt check skipped', [
                    'error' => $e->getMessage(),
                    'ticket_count' => count($idChunk),
                ]);
            }
        }

        return $collected;
    }

    /**
     * Strip any non-routing or sensitive values before dispatching over external push transport.
     */
    private function sanitizePayloadData(array $data): array
    {
        $allowedKeys = [
            'type',
            'event_key',
            'order_id',
            'medicine_id',
            'pharmacy_id',
            'status',
            'action_url',
            'url',
        ];

        $out = [];
        foreach ($allowedKeys as $key) {
            if (!array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
                continue;
            }
            $value = $data[$key];
            if (is_scalar($value)) {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
