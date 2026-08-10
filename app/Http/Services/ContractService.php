<?php

namespace App\Http\Services;

use App\Http\Resources\ContractResource;
use App\Models\Contract;
use App\Notifications\ContractNotification;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\HttpTransportException;

class ContractService extends Service
{
    /*
     * Fetch All Contracts
     */
    public function index($request)
    {
        $contractsQuery = new Contract;

        $contractsQuery = $this->search($contractsQuery, $request);

        $contracts = $contractsQuery
            ->orderBy("id", "DESC")
            ->paginate(20);

        return ContractResource::collection($contracts);
    }

    /*
     * Fetch Contract
     */
    public function show($id)
    {
        $contract = Contract::findOrFail($id);

        return new ContractResource($contract);
    }

    /*
     * Save Contract
     */
    public function store($request)
    {
        // Check if there's already an active contract for this user_unit
        $existingActive = Contract::where("user_unit_id", $request->userUnitId)
            ->where("status", "active")
            ->exists();

        if ($existingActive) {
            return [false, "An active contract already exists for this tenancy", "", 422];
        }

        $contract = new Contract;
        $contract->user_unit_id = $request->userUnitId;
        $contract->type = $request->type;
        $contract->start_date = $request->startDate;
        $contract->end_date = $request->endDate;
        $contract->rent_amount = $request->rentAmount;
        $contract->deposit_amount = $request->depositAmount ?? 0;
        $contract->payment_frequency = $request->paymentFrequency ?? "monthly";
        $contract->terms = $request->terms;
        $contract->status = $request->status ?? "active";
        $contract->auto_renew = $request->autoRenew ?? false;
        $contract->notice_period_days = $request->noticePeriodDays ?? 30;
        $contract->created_by = $this->id;

        $saved = $contract->save();

        return [$saved, "Contract Created Successfully", $contract];
    }

    /*
     * Update Contract
     */
    public function update($request, $id)
    {
        $contract = Contract::findOrFail($id);

        if ($request->filled("type")) {
            $contract->type = $request->type;
        }

        if ($request->filled("startDate")) {
            $contract->start_date = $request->startDate;
        }

        if ($request->filled("endDate")) {
            $contract->end_date = $request->endDate;
        }

        if ($request->filled("rentAmount")) {
            $contract->rent_amount = $request->rentAmount;
        }

        if ($request->filled("depositAmount")) {
            $contract->deposit_amount = $request->depositAmount;
        }

        if ($request->filled("paymentFrequency")) {
            $contract->payment_frequency = $request->paymentFrequency;
        }

        if ($request->filled("terms")) {
            $contract->terms = $request->terms;
        }

        if ($request->filled("status")) {
            $contract->status = $request->status;
        }

        if ($request->filled("autoRenew")) {
            $contract->auto_renew = $request->autoRenew;
        }

        if ($request->filled("noticePeriodDays")) {
            $contract->notice_period_days = $request->noticePeriodDays;
        }

        // Handle termination
        if ($request->filled("terminate")) {
            $contract->status = "terminated";
            $contract->terminated_at = Carbon::now();
            $contract->termination_reason = $request->terminationReason;
        }

        // Handle signing
        if ($request->filled("sign")) {
            $contract->signed_at = Carbon::now();
        }

        $saved = $contract->save();

        return [$saved, "Contract Updated Successfully", $contract];
    }

    /*
     * Destroy Contract
     */
    public function destroy($id)
    {
        $contract = Contract::findOrFail($id);

        $deleted = $contract->delete();

        return [$deleted, "Contract Deleted Successfully", $contract];
    }

    /*
     * Expire Contracts that have passed their end date
     */
    public function expireContracts()
    {
        $expired = Contract::where("status", "active")
            ->whereNotNull("end_date")
            ->whereDate("end_date", "<", Carbon::today())
            ->get();

        $count = 0;

        foreach ($expired as $contract) {
            if ($contract->auto_renew) {
                // Create a renewal contract
                $newContract = new Contract;
                $newContract->user_unit_id = $contract->user_unit_id;
                $newContract->type = "renewal";
                $newContract->start_date = Carbon::parse($contract->getRawOriginal('end_date'))->addDay();
                $newContract->end_date = Carbon::parse($contract->getRawOriginal('end_date'))
                    ->addDay()
                    ->addYear();
                $newContract->rent_amount = $contract->getRawOriginal('rent_amount');
                $newContract->deposit_amount = $contract->getRawOriginal('deposit_amount');
                $newContract->payment_frequency = $contract->payment_frequency;
                $newContract->terms = $contract->terms;
                $newContract->auto_renew = $contract->auto_renew;
                $newContract->notice_period_days = $contract->notice_period_days;
                $newContract->created_by = $contract->created_by;
                $newContract->save();
            }

            $contract->status = "expired";
            // Access raw value since accessor formats it
            $contract->save();
            $count++;
        }

        return $count;
    }

    /*
     * Handle Search
     */
    public function search($query, $request)
    {
        if ($request->filled("propertyId") && $request->propertyId != "undefined") {
            $propertyIds = explode(",", $request->propertyId);

            $isSuper = in_array("All", $propertyIds);

            if (! $isSuper) {
                $query = $query->whereHas("userUnit.unit.property", function ($query) use ($propertyIds) {
                    $query->whereIn("id", $propertyIds);
                });
            }
        }

        if ($request->filled("unitId") && $request->unitId != "undefined") {
            $unitId = $request->input("unitId");

            $query = $query->whereHas("userUnit.unit", function ($query) use ($unitId) {
                $query->where("id", $unitId);
            });
        }

        $unit = $request->input("unit");

        if ($request->filled("unit")) {
            $query = $query
                ->whereHas("userUnit.unit", function ($query) use ($unit) {
                    $query->where("name", "LIKE", "%".$unit."%");
                });
        }

        $tenant = $request->input("tenant");

        if ($request->filled("tenant")) {
            $query = $query
                ->whereHas("userUnit.user", function ($query) use ($tenant) {
                    $query->where("name", "LIKE", "%".$tenant."%");
                });
        }

        if ($request->filled("userUnitId")) {
            $query = $query->where("user_unit_id", $request->input("userUnitId"));
        }

        $status = $request->input("status");

        if ($request->filled("status")) {
            $query = $query->where("status", $status);
        }

        $type = $request->input("type");

        if ($request->filled("type")) {
            $query = $query->where("type", $type);
        }

        return $query;
    }

    /*
     * Generate Contract PDF
     */
    public function generatePdf($id)
    {
        $contract = Contract::findOrFail($id);

        // This looks for resources/views/contracts/pdf.blade.php
        $pdf = Pdf::loadView('contracts.pdf', compact('contract'));

        return $pdf;
    }

    /*
     * Send Contract by Email
     */
    public function sendEmail($id)
    {
        $contract = Contract::findOrFail($id);

        try {
            DB::beginTransaction();

            $generatedPdf = $this->generatePdf($id);

            $pdf = $generatedPdf->output();

            $contract->userUnit->user->notify(new ContractNotification($contract, $pdf));

            // Save Email
            $emailService = new EmailService;

            $request = new Request([
                "userUnitId" => $contract->userUnit->id,
                "contractId" => $contract->id,
                "email" => $contract->userUnit->user->email,
                "model" => $contract,
            ]);

            $emailService->store($request);

            DB::commit();
        } catch (HttpTransportException $exception) {
            DB::rollBack();

            Log::error("Contract Email Error: ".$exception->getMessage());

            throw $exception;
        }

        return ["Success", "Contract Email Sent Successfully", $contract];
    }
}
