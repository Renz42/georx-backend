<?php

namespace App\Console\Commands;

use App\Models\EmailOtpChallenge;
use Illuminate\Console\Command;

class PruneExpiredOtpChallenges extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'otp:prune {--hours=24 : Delete challenges expired or consumed more than this many hours ago}';

    /**
     * The console command description.
     */
    protected $description = 'Safely delete expired or consumed email OTP challenges without affecting user accounts';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $cutoff = now()->subHours($hours);

        $deletedCount = EmailOtpChallenge::where(function ($query) use ($cutoff) {
            $query->whereNotNull('consumed_at')->where('consumed_at', '<', $cutoff)
                ->orWhere(function ($q) use ($cutoff) {
                    $q->whereNull('consumed_at')->where('expires_at', '<', $cutoff);
                });
        })->delete();

        $this->info("Successfully pruned {$deletedCount} stale OTP challenges older than {$hours} hours.");
        return Command::SUCCESS;
    }
}
