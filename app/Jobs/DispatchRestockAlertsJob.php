<?php

namespace App\Jobs;

use App\Models\StockAlert;
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
        // 1. Restock Event Cache Lock (60-second idempotency lock)
        $lockKey = "restock_lock:{$this->pharmacyId}:{$this->medicineId}";
        if (!Cache::add($lockKey, true, 60)) {
            \Log::info("Restock dispatch lock active for Pharmacy {$this->pharmacyId}, Medicine {$this->medicineId}. Suppressing duplicate job.");
            return;
        }

        // 2. Fetch active alert IDs in chunks of 500
        StockAlert::where('pharmacy_id', $this->pharmacyId)
            ->where('medicine_id', $this->medicineId)
            ->where('is_active', true)
            ->chunkById(500, function ($alerts) {
                $alertIds = $alerts->pluck('id')->toArray();
                if (!empty($alertIds)) {
                    SendRestockChunkJob::dispatchSync($this->pharmacyId, $this->medicineId, $alertIds);
                }
            });
    }
}
