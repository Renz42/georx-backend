<?php

namespace App\Jobs;

use App\Models\StockAlert;
use App\Models\UserFavorite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class DispatchRestockAlertsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $pharmacyId;
    public int $medicineId;
    public int $uniqueFor = 60;

    /**
     * Get the unique key for the job.
     */
    public function uniqueId(): string
    {
        return "restock_{$this->pharmacyId}_{$this->medicineId}";
    }

    /**
     * Create a new job instance.
     */
    public function __construct(int $pharmacyId, int $medicineId)
    {
        $this->pharmacyId = $pharmacyId;
        $this->medicineId = $medicineId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // 1. Restock Event Cache Lock (configurable idempotency window)
        $dedupSeconds = max(5, (int) config('services.notifications.restock_dedup_seconds', 60));
        $lockKey = "restock_lock:{$this->pharmacyId}:{$this->medicineId}";
        if (!Cache::add($lockKey, true, $dedupSeconds)) {
            \Log::info("Restock dispatch lock active for Pharmacy {$this->pharmacyId}, Medicine {$this->medicineId}. Suppressing duplicate job.");
            return;
        }

        // 2. Collect matching active StockAlert subscriptions (specific pharmacy OR global pharmacy_id = null)
        $alertQuery = StockAlert::query()
            ->where(function ($q) {
                $q->where('pharmacy_id', $this->pharmacyId)->orWhereNull('pharmacy_id');
            })
            ->where('medicine_id', $this->medicineId)
            ->where('is_active', true);

        $alertRows = $alertQuery->get(['id', 'user_id']);
        $alertIds = $alertRows->pluck('id')->all();
        $alertUserIds = $alertRows->pluck('user_id')->all();

        if (!empty($alertIds)) {
            // Preserve recurring watches by keeping is_active = true and stamping notified_at
            StockAlert::whereIn('id', $alertIds)->update([
                'notified_at' => now(),
            ]);
        }

        // 3. Include users who favorited this pharmacy
        $favoriteUserIds = UserFavorite::query()
            ->where('pharmacy_id', $this->pharmacyId)
            ->pluck('user_id')
            ->all();

        // 4. Deduplicate users across specific watches, global watches, and favorites
        $recipientUserIds = array_values(array_unique(array_map('intval', array_merge($alertUserIds, $favoriteUserIds))));
        if (empty($recipientUserIds)) {
            return;
        }

        foreach (array_chunk($recipientUserIds, 500) as $userChunk) {
            SendRestockChunkJob::dispatchSync($this->pharmacyId, $this->medicineId, [], $userChunk);
        }
    }
}
