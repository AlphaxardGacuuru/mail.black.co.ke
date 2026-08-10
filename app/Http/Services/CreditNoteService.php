<?php

namespace App\Http\Services;

use App\Http\Resources\CreditNoteResource;
use App\Models\CreditNote;
use Illuminate\Support\Facades\DB;

class CreditNoteService extends Service
{
    /*
     * Fetch All Credit Notes
     */
    public function index($request)
    {
        $creditNotesQuery = new CreditNote;

        $creditNotesQuery = $this->search($creditNotesQuery, $request);

        $sum = $creditNotesQuery->sum("amount");

        $creditNotes = $creditNotesQuery
            ->orderBy("month", "DESC")
            ->orderBy("year", "DESC")
            ->orderBy("id", "DESC")
            ->paginate(20);

        return CreditNoteResource::collection($creditNotes)
            ->additional(["sum" => number_format($sum)]);
    }

    /*
     * Fetch Credit Note
     */
    public function show($id)
    {
        $creditNote = CreditNote::find($id);

        return new CreditNoteResource($creditNote);
    }

    /*
     * Save Credit Note
     */
    public function store($request)
    {
        foreach ($request->userUnitIds as $userUnitId) {
            $creditNote = new CreditNote;
            $creditNote->user_unit_id = $userUnitId;
            $creditNote->description = $request->description;
            $creditNote->amount = $request->amount;
            $creditNote->month = $request->month;
            $creditNote->year = $request->year;
            $creditNote->created_by = $this->id;

            $saved = DB::transaction(function () use ($creditNote) {
                $saved = $creditNote->save();

                // Update Invoice Status
                $this->updateInvoiceStatus($creditNote->user_unit_id);

                return $saved;
            });
        }

        return [$saved, "Credit Note Created Successfully", $creditNote];
    }

    /*
     * Update Credit Note
     */
    public function update($request, $id)
    {
        $creditNote = CreditNote::find($id);

        if ($request->filled("amount")) {
            $creditNote->amount = $request->amount;
        }

        if ($request->filled("description")) {
            $creditNote->description = $request->description;
        }

        if ($request->filled("month")) {
            $creditNote->month = $request->month;
        }

        if ($request->filled("year")) {
            $creditNote->year = $request->year;
        }

        $saved = DB::transaction(function () use ($creditNote) {
            $saved = $creditNote->save();

            // Update Invoice Status
            $this->updateInvoiceStatus($creditNote->user_unit_id);

            return $saved;
        });

        return [$saved, "Credit Note updated", $creditNote];
    }

    /*
     * Destroy Credit Note
     */
    public function destroy($id)
    {
        $creditNote = CreditNote::findOrFail($id);

        $deleted = DB::transaction(function () use ($creditNote) {
            $deleted = $creditNote->delete();

            // Update Invoice Status
            $this->updateInvoiceStatus($creditNote->user_unit_id);

            return $deleted;
        });

        return [$deleted, "Credit Note Deleted Successfully", $creditNote];
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

    public function invoiceService()
    {
        return new InvoiceService;
    }
}
