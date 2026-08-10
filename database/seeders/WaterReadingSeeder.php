<?php

namespace Database\Seeders;

use App\Models\UserUnit;
use App\Models\WaterReading;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class WaterReadingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        WaterReading::truncate();

        $reading = rand(5, 10);

        $userUnits = UserUnit::get();

        foreach ($userUnits as $key => $userUnit) {

            $startMonth = Carbon::createFromFormat("d M Y", $userUnit->occupied_at)->format("m");

            if ($userUnit->vacated_at) {
                $endMonth = Carbon::createFromFormat("d M Y", $userUnit->vacated_at)->month;
            } else {
                $endMonth = Carbon::now()->month;
            }

            for ($month = $startMonth; $month <= $endMonth; $month++) {

                $year = Carbon::createFromFormat("d M Y", $userUnit->occupied_at)
                    ->addMonths($month - $startMonth)
                    ->year;

                $lastMonth = $month - 1;

                // Get Last Water Reading
                $previousReadingQuery = WaterReading::where("user_unit_id", $userUnit->id)
                    ->where("month", $lastMonth)
                    ->where("year", $year)
                    ->first();

                $previouReading = $previousReadingQuery ? $previousReadingQuery->reading : $reading;

                $usage = $reading - $previouReading;

                $waterBillRate = UserUnit::find($userUnit->id)
                    ->unit
                    ->property
                    ->water_bill_rate;

                $types = ["council", "borehole", "tanker"];

                $type = $types[array_rand($types)];

                $bill = $usage * $waterBillRate->$type;

                WaterReading::factory()
                    ->create([
                        "user_unit_id" => $userUnit->id,
                        "type" => $type,
                        "reading" => $reading,
                        "month" => $month,
                        "year" => $year,
                        "usage" => $usage,
                        "bill" => $bill,
                    ]);

                // Increment Reading
                $reading += rand(5, 10);
            }
        }
    }
}
