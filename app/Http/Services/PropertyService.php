<?php

namespace App\Http\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use App\Http\Resources\PropertyResource;
use App\Models\Property;

class PropertyService extends Service
{
    /*
     * Get All Properties
     */
    public function index(Request $request)
    {
        if ($request->filled("idAndName")) {
            $propertyQuery = Property::query()->select(["id", "name"]);

            $propertyQuery = $this->search($propertyQuery, $request);

            $properties = $propertyQuery
                ->orderBy("id", "DESC")
                ->get();

            return response([
                "data" => $properties,
            ], 200);
        }

        $propertyQuery = Property::query()->with([
            "user:id,name",
        ]);

        $propertyQuery = $this->search($propertyQuery, $request);

        [$sortColumn, $sortDirection] = $this->sort($request);

        $properties = $propertyQuery
            ->orderBy($sortColumn, $sortDirection)
            ->paginate($request->integer("perPage", 15));

        return PropertyResource::collection($properties);
    }

    /*
     * Get One Property
     */
    public function show(string $id)
    {
        $property = Property::findOrFail($id);

        return new PropertyResource($property);
    }

    /*
     * Store Property
     */
    public function store(Request $request)
    {
        // Check if User has reached subscription limit
        $subsciptionMaxProperties = auth("sanctum")
            ->user()
            ->activeSubscription()
            ?->max_properties ?? 0;

        $currentPropertyCount = auth("sanctum")
            ->user()
            ->properties
            ->count();

        if ($currentPropertyCount >= $subsciptionMaxProperties) {
            return [
                false,
                "You have reached your subscription limit of ".$subsciptionMaxProperties." properties.",
                null,
            ];
        }

        $property = new Property;
        $property->user_id = $this->id;
        $property->name = $request->input("name");
        $property->location = $request->input("location");
        $property->deposit_formula = $request->input("depositFormula");
        $property->service_charge = $request->input("serviceCharge");
        $property->water_bill_rate = $request->input("waterBillRate");
        $property->invoice_date = $request->input("invoiceDate");
        $property->invoice_reminder_duration = $request->input("invoiceReminderDuration");
        $property->contract_terms = $request->input("contractTerms");
        $property->email = $request->input("email");
        $property->sms = $request->input("sms");

        $saved = $property->save();

        $message = $property->name." Created Successfully";

        return [$saved, $message, $property];
    }

    /*
     * Update Property
     */
    public function update(Request $request, string $id)
    {
        $property = Property::findOrFail($id);

        if ($request->filled("name")) {
            $property->name = $request->input("name");
        }

        if ($request->filled("location")) {
            $property->location = $request->input("location");
        }

        if ($request->filled("depositFormula")) {
            $property->deposit_formula = $request->input("depositFormula");
        }

        if ($request->filled("serviceCharge")) {
            $property->service_charge = $request->input("serviceCharge");
        }

        if ($request->filled("waterBillRate")) {
            $property->water_bill_rate = $request->input("waterBillRate");
        }

        if ($request->filled("invoiceDate")) {
            $property->invoice_date = $request->input("invoiceDate");
        }

        if ($request->filled("invoiceReminderDuration")) {
            $property->invoice_reminder_duration = $request->input("invoiceReminderDuration");
        }

        if ($request->filled("contractTerms")) {
            $property->contract_terms = $request->input("contractTerms");
        }

        if ($request->filled("email")) {
            $property->email = $request->input("email");
        }

        if ($request->filled("sms")) {
            $property->sms = $request->input("sms");
        }

        $saved = $property->save();

        $message = $property->name." Updated Successfully";

        return [$saved, $message, $property];
    }

    /*
     * Destroy
     */
    public function destroy(string $id)
    {
        $property = Property::findOrFail($id);

        $deleted = $property->delete();

        $message = $property->name." Deleted Successfully";

        return [$deleted, $message, $property];
    }

    /*
     * Destroy Many
     */
    public function destroyMany(array $ids)
    {
        $deletedCount = Property::whereIn("id", $ids)->delete();

        $message = $deletedCount === 1
            ? "1 property deleted successfully"
            : "{$deletedCount} properties deleted successfully";

        return [$deletedCount, $message];
    }

    /*
     * Search
     */
    public function search(Builder $query, Request $request)
    {
        if ($request->filled("propertyId")) {
            $propertyIds = explode(",", $request->propertyId);

            $query->whereIn("id", $propertyIds);
        }

        if ($request->filled("userId")) {
            $assignedPropertyIds = $request->assignedPropertyIds ? explode(",", $request->assignedPropertyIds) : [];

            $isSuper = in_array("All", $assignedPropertyIds);

            if (! $isSuper) {
                $query
                    ->where("user_id", $request->userId)
                    ->orWhereIn("id", $assignedPropertyIds);
            }
        }

        if ($request->filled("name")) {
            $query->where("name", "LIKE", "%".$request->name."%");
        }

        return $query;
    }

    /*
     * Resolve the sort column/direction from the request, falling back to
     * the default "id desc" when no valid sort is requested.
     */
    protected function sort(Request $request): array
    {
        $sortableColumns = [
            "name" => "name",
            "location" => "location",
            "depositFormula" => "deposit_formula",
            "unitCount" => "unit_count",
            "invoiceDate" => "invoice_date",
        ];

        $sortColumn = $sortableColumns[$request->query("sort")] ?? null;

        if (! $sortColumn) {
            return ["id", "desc"];
        }

        $sortDirection = $request->query("direction") === "asc" ? "asc" : "desc";

        return [$sortColumn, $sortDirection];
    }
}
