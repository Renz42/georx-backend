<?php

namespace App\Console\Commands;

use App\Models\Pharmacy;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\ReservationExpired;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireReservations extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'reservations:expire';

    /**
     * The console command description.
     */
    protected $description = 'Expire reservations that are past 1 hour of their scheduled pickup time';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Find pending or approved reservations where scheduled_pickup_at + 1 hour < now
        // Driver-aware date arithmetic: MySQL uses DATE_ADD, PostgreSQL uses INTERVAL syntax
        $driver = DB::getDriverName();
        $intervalExpression = $driver === 'pgsql'
            ? "scheduled_pickup_at + INTERVAL '1 hour' < NOW()"
            : 'DATE_ADD(scheduled_pickup_at, INTERVAL 1 HOUR) < NOW()';

        $expired = Reservation::whereIn('status', ['pending', 'approved'])
            ->whereRaw($intervalExpression)
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No expired reservations found.');
            return self::SUCCESS;
        }

        $count = 0;

        foreach ($expired as $reservation) {
            // Restore stock back to the pharmacy
            $pharmacy = Pharmacy::find($reservation->pharmacy_id);

            if ($pharmacy) {
                $medicine = $pharmacy->medicines()
                    ->withPivot('quantity_on_hand')
                    ->where('medicines.id', $reservation->medicine_id)
                    ->first();

                if ($medicine) {
                    $newStock = $medicine->pivot->quantity_on_hand + $reservation->quantity;
                    $pharmacy->medicines()->updateExistingPivot($reservation->medicine_id, [
                        'quantity_on_hand' => $newStock,
                        'is_available' => $newStock > 0,
                    ]);
                }
            }

            // Mark as cancelled
            $reservation->update(['status' => 'cancelled']);

            // Notify User
            if ($reservation->user) {
                $reservation->user->notify(new ReservationExpired($reservation, 'user'));
            }

            // Notify Pharmacy Admin(s)
            if ($pharmacy) {
                $admins = User::where('pharmacy_id', $pharmacy->id)
                    ->where('role', User::ROLE_PHARMACY_ADMIN)
                    ->get();
                foreach ($admins as $admin) {
                    $admin->notify(new ReservationExpired($reservation, 'pharmacy'));
                }
            }

            $count++;
        }

        $this->info("Successfully expired {$count} reservation(s) and restored stock.");
        return self::SUCCESS;
    }
}
