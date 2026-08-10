<?php

namespace App\Http\Services;

use App\Http\Resources\UnitResource;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;

class UnitService extends Service
{
    /*
     * Get All Units
     */
    public function index($request)
    {
        $unitQuery = new Unit;

        $unitQuery = $this->search($unitQuery, $request);

        $units = $unitQuery
            ->orderBy("id", "DESC")
            ->paginate(20)
            ->appends([
                "propertyId" => $request->propertyId,
            ]);

        return UnitResource::collection($units);
    }

    /*
     * Get One Unit
     */
    public function show($id)
    {
        $unit = Unit::findOrFail($id);

        return new UnitResource($unit);
    }

    /*
     * Store Unit
     */
    public function store($request)
    {
        // Check if User has reached subscription limit
        $subsciptionMaxUnits = auth("sanctum")
            ->user()
            ->activeSubscription()
            ?->max_units ?? 0;

        $currentUnitCount = auth("sanctum")
            ->user()
            ->properties
            ->reduce(function ($carry, $property) {
                return $carry + $property->units->count();
            }, 0);

        if ($currentUnitCount >= $subsciptionMaxUnits) {
            return [
                false,
                "You have reached your subscription limit of ".$subsciptionMaxUnits." units.",
                null,
            ];
        }

        $unit = new Unit;
        $unit->property_id = $request->input("propertyId");
        $unit->name = $request->input("name");
        $unit->rent = $request->input("rent");
        $unit->deposit = $request->input("deposit");
        $unit->service_charge = $request->input("serviceCharge");
        $unit->type = $request->input("type");
        $unit->bedrooms = $request->input("bedrooms");
        $unit->size = $request->input("size");
        $unit->ensuite = $request->input("ensuite");
        $unit->dsq = $request->input("dsq");

        $saved = DB::transaction(function () use ($unit, $request) {
            $saved = $unit->save();

            Property::find($request->propertyId)
                ->increment("unit_count");

            return $saved;
        });

        $message = $unit->name." Created Successfully";

        return [$saved, $message, $unit];
    }

    /*
     * Update Unit
     */
    public function update($request, $id)
    {
        $unit = Unit::findOrFail($id);

        if ($request->filled("name")) {
            $unit->name = $request->input("name");
        }

        if ($request->filled("rent")) {
            $unit->rent = $request->input("rent");
        }

        if ($request->filled("deposit")) {
            $unit->deposit = $request->input("deposit");
        }

        if ($request->filled("serviceCharge")) {
            $unit->service_charge = $request->input("serviceCharge");
        }

        if ($request->filled("type")) {
            $unit->type = $request->input("type");
        }

        if ($request->filled("bedrooms")) {
            $unit->bedrooms = $request->input("bedrooms");
            $unit->size = null;
        }

        if ($request->filled("size")) {
            $unit->size = $request->input("size");
            $unit->bedrooms = null;
        }

        if ($request->filled("ensuite")) {
            $unit->ensuite = $request->input("ensuite");
        }

        if ($request->filled("dsq")) {
            $unit->dsq = $request->input("dsq");
        }

        $saved = $unit->save();

        $message = $unit->name." Updated Successfully";

        return [$saved, $message, $unit];
    }

    /*
     * Destroy
     */
    public function destroy($id)
    {
        $unit = Unit::findOrFail($id);

        $deleted = DB::transaction(function () use ($unit) {
            Property::find($unit->property_id)
                ->decrement("unit_count");

            return $unit->delete();
        });

        $message = $unit->name." Deleted Successfully";

        return [$deleted, $message, $unit];
    }

    /*
     * Search
     */
    public function search($query, $request)
    {
        if ($request->status != "vacant") {
            $propertyIds = explode(",", $request->propertyId);

            $isSuper = in_array("All", $propertyIds);

            if (! $isSuper) {
                $query = $query->whereIn("property_id", $propertyIds);
            }
        }

        if ($request->filled("name")) {
            $query = $query->where("name", "LIKE", "%".$request->input("name")."%");
        }

        if ($request->filled("type")) {
            $query = $query->where("type", $request->type);
        }

        if ($request->filled("status")) {
            $query = $query->where("status", $request->status);
        }

        return $query;
    }
}
