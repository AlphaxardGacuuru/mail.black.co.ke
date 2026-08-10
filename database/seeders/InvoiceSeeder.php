<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\UserProperty;
use App\Models\UserUnit;
use App\Models\WaterReading;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class InvoiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Invoice::all()->each(fn ($invoice) => $invoice->delete());
        Payment::all()->each(fn ($payment) => $payment->delete());

        // Fetch the user units for the current month
        $userUnits = UserUnit::limit(1)->get();

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

                $staffId = UserProperty::where("property_id", $userUnit->unit->property_id)
                    ->get()
                    ->random()
                    ->user_id;

                $this->createRentInvoices($userUnit, $staffId, $key, $month, $year);
                $this->createWaterInvoices($userUnit, $staffId, $key, $month, $year);
                $this->createServiceChargeInvoices($userUnit, $staffId, $key, $month, $year);
            }
        }
    }

    public function createRentInvoices($userUnit, $staffId, $key, $month, $year)
    {
        $amount = $userUnit->unit->rent;

        Invoice::factory()
            ->create([
                "user_unit_id" => $userUnit->id,
                "type" => "rent",
                "amount" => $amount,
                "balance" => $key < 10 ? $amount : 0,
                "paid" => $key < 10 ? 0 : $amount,
                "status" => $key < 10 ? "not_paid" : "paid",
                "month" => $month,
                "year" => $year,
                "created_by" => $staffId,
            ]);

        Payment::factory()
            ->create([
                "user_unit_id" => $userUnit->id,
                "amount" => $amount,
                "month" => $month,
                "year" => $year,
                "created_by" => $staffId,
            ]);
    }

    public function createWaterInvoices($userUnit, $staffId, $key, $month, $year)
    {
        // Get Water Bill
        $amount = WaterReading::where("user_unit_id", $userUnit->id)
            ->where("month", $month)
            ->where("year", $year)
            ->first()
            ?->bill;

        Invoice::factory()
            ->create([
                "user_unit_id" => $userUnit->id,
                "type" => "water",
                "amount" => $amount,
                "balance" => $key < 10 ? $amount : 0,
                "paid" => $key < 10 ? 0 : $amount,
                "status" => $key < 10 ? "not_paid" : "paid",
                "month" => $month,
                "year" => $year,
                "created_by" => $staffId,
            ]);

        Payment::factory()
            ->create([
                "user_unit_id" => $userUnit->id,
                "amount" => $amount,
                "month" => $month,
                "year" => $year,
                "created_by" => $staffId,
            ]);
    }

    public function createServiceChargeInvoices($userUnit, $staffId, $key, $month, $year)
    {
        $amount = $userUnit
            ->unit
            ->property
            ->service_charge
            ->service;

        Invoice::factory()
            ->create([
                "user_unit_id" => $userUnit->id,
                "type" => "service_charge",
                "amount" => $amount,
                "balance" => $key < 10 ? $amount : 0,
                "paid" => $key < 10 ? 0 : $amount,
                "status" => $key < 10 ? "not_paid" : "paid",
                "month" => $month,
                "year" => $year,
                "created_by" => $staffId,
            ]);

        Payment::factory()
            ->create([
                "user_unit_id" => $userUnit->id,
                "amount" => $amount,
                "month" => $month,
                "year" => $year,
                "created_by" => $staffId,
            ]);
    }
}
