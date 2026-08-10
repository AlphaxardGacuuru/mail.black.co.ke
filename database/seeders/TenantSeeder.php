<?php

namespace Database\Seeders;

use App\Models\Unit;
use App\Models\UserUnit;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $units = Unit::all();

        foreach ($units as $unit) {
            // Start from the oldest date and work forward to ensure no overlaps
            $currentDate = Carbon::now()->subMonths(24); // Start from 24 months ago

            for ($i = 0; $i <= 3; $i++) {
                if ($i == 3) {
                    // Current tenant - occupied from calculated date, no vacated_at
                    $occupiedAt = $currentDate->copy()->startOfMonth();

                    $userUnit = UserUnit::factory()
                        ->create([
                            "unit_id" => $unit->id,
                            "occupied_at" => $occupiedAt,
                            "vacated_at" => null, // Current tenant, still occupied
                        ]);

                    // Set Unit as occupied
                    $unit = $userUnit->unit;
                    $unit->status = "occupied";
                    $unit->save();
                } else {
                    // Previous tenants - create sequential, non-overlapping periods
                    $occupiedAt = $currentDate->copy()->startOfMonth();
                    $tenancyDurationMonths = 6; // Fixed tenancy duration of 6 months
                    $vacatedAt = $occupiedAt->copy()->addMonths($tenancyDurationMonths)->endOfMonth();

                    UserUnit::factory()
                        ->create([
                            "unit_id" => $unit->id,
                            "occupied_at" => $occupiedAt,
                            "vacated_at" => $vacatedAt,
                        ]);

                    // Move current date forward for next tenant (add a gap between tenants)
                    $currentDate = $vacatedAt->copy()->addDays(rand(1, 30)); // 1-30 day gap between tenants
                }
            }
        }
    }
}
