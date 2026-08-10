<?php

namespace App\Http\Services;

use App\Http\Resources\DeductionResource;
use App\Models\Deduction;
use Illuminate\Support\Facades\DB;

class DeductionService extends Service
{
    /*
     * Fetch All Deductions
     */
    public function index($request)
    {
        $deductionsQuery = new Deduction;

        $deductionsQuery = $this->search($deductionsQuery, $request);

        $sum = $deductionsQuery->sum("amount");

        $deductions = $deductionsQuery
            ->orderBy("month", "DESC")
            ->orderBy("year", "DESC")
            ->orderBy("id", "DESC")
            ->paginate(20);

        return DeductionResource::collection($deductions)
            ->additional(["sum" => number_format($sum)]);
    }

    /*
     * Fetch Deduction
     */
    public function show($id)
    {
        $deduction = Deduction::find($id);

        return new DeductionResource($deduction);
    }

    /*
     * Save Deduction
     */
    public function store($request)
    {
        foreach ($request->userUnitIds as $userUnitId) {
            $deduction = new Deduction;
            $deduction->user_unit_id = $userUnitId;
            $deduction->description = $request->description;
            $deduction->amount = $request->amount;
            $deduction->month = $request->month;
            $deduction->year = $request->year;
            $deduction->created_by = $this->id;

            $saved = DB::transaction(function () use ($deduction) {
                $saved = $deduction->save();

                // Update Invoice Status
                $this->updateInvoiceStatus($deduction->user_unit_id);

                return $saved;
            });
        }

        return [$saved, "Deduction Created Successfully", $deduction];
    }

    /*
     * Update Deduction
     */
    public function update($request, $id)
    {
        $deduction = Deduction::find($id);

        if ($request->filled("amount")) {
            $deduction->amount = $request->amount;
        }

        if ($request->filled("description")) {
            $deduction->description = $request->description;
        }

        if ($request->filled("month")) {
            $deduction->month = $request->month;
        }

        if ($request->filled("year")) {
            $deduction->year = $request->year;
        }

        $saved = DB::transaction(function () use ($deduction) {
            $saved = $deduction->save();

            // Update Invoice Status
            $this->updateInvoiceStatus($deduction->user_unit_id);

            return $saved;
        });

        return [$saved, "Deduction updated", $deduction];
    }

    /*
     * Destroy Deduction
     */
    public function destroy($id)
    {
        $deduction = Deduction::findOrFail($id);

        $deleted = DB::transaction(function () use ($deduction) {
            $deleted = $deduction->delete();

            // Update Invoice Status
            $this->updateInvoiceStatus($deduction->user_unit_id);

            return $deleted;
        });

        return [$deleted, "Deduction Deleted Successfully", $deduction];
    }

    /*
     * Handle Search
     */
    public function search($query, $request)
    {
        if ($request->propertyId != "undefined") {
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

        if ($request->filled("tenantId") && $request->tenantId != "undefined") {
            $tenantId = $request->input("tenantId");

            $query = $query->whereHas("userUnit", function ($query) use ($tenantId) {
                $query->where("user_id", $tenantId);
            });
        }

        $userUnitId = $request->input("userUnitId");

        if ($request->filled("userUnitId")) {
            $query = $query->where("user_unit_id", $userUnitId);
        }

        $month = $request->input("month");

        if ($request->filled("month")) {
            $query = $query->where("month", $month);
        }

        $year = $request->input("year");

        if ($request->filled("year")) {
            $query = $query->where("year", $year);
        }

        $type = $request->input("type");

        if ($request->filled("type")) {
            $query = $query->where("type", $type);
        }

        $startMonth = $request->input("startMonth");
        $endMonth = $request->input("endMonth");
        $startYear = $request->input("startYear");
        $endYear = $request->input("endYear");

        if ($request->filled("startMonth")) {
            $query = $query->where("month", ">=", $startMonth);
        }

        if ($request->filled("endMonth")) {
            $query = $query->where("month", "<=", $endMonth);
        }

        if ($request->filled("startYear")) {
            $query = $query->where("year", ">=", $startYear);
        }

        if ($request->filled("endYear")) {
            $query = $query->where("year", "<=", $endYear);
        }

        return $query;
    }
}
