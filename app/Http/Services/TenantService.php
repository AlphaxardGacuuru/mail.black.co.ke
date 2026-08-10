<?php

namespace App\Http\Services;

use App\Models\Unit;
use App\Models\User;
use App\Models\UserUnit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TenantService extends Service
{
    /*
     * Get All Tenants
     */
    public function index($request)
    {
        if ($request->filled("idAndName")) {
            $tenantQuery = new UserUnit;

            // Pass through search to filter by property
            $tenantQuery = $this->search($tenantQuery, $request);

            $tenants = $tenantQuery->whereNull("vacated_at")
                ->get();

            return [true, $tenants->count()." Tenants Retrieved Successfully", $tenants];
        }

        $tenantQuery = new UserUnit;

        $tenantQuery = $this->search($tenantQuery, $request);

        $tenants = $tenantQuery->orderBy("id", "DESC")
            ->paginate(20)
            ->appends([
                "propertyId" => $request->propertyId,
                "unitId" => $request->unitId,
            ]);

        return [true, $tenants->total()." Tenants Retrieved Successfully", $tenants];
    }

    /*
     * Get One Tenant
     */
    public function show($id)
    {
        $tenant = UserUnit::findOrFail($id);

        return [true, "Tenant Retrieved Successfully", $tenant];
    }

    /*
     * Store
     */
    public function store($request)
    {
        $tenantQuery = User::where("email", $request->email);

        // Check if User exists
        $doesntExist = $tenantQuery->doesntExist();

        if ($doesntExist) {
            $tenant = new User;
            $tenant->name = $request->input("name");
            $tenant->email = $request->input("email");
            $tenant->phone = $request->input("phone");
            $tenant->gender = $request->input("gender");
            $tenant->password = Hash::make($request->input("email"));
        } else {
            $tenant = $tenantQuery->first();

            // Check if user is occupying elsewhere
            $alreadyATenantElsewhere = UserUnit::where("user_id", $tenant->id)
                ->whereNull("vacated_at")
                ->exists();

            if ($alreadyATenantElsewhere) {
                return [false, $tenant->name." already a tenant elsewhere", "", 422];
            }
        }

        // Check if Unit is already occupied
        $unitAlreadyOccupied = UserUnit::where("unit_id", $request->unitId)
            ->whereNull("vacated_at")
            ->exists();

        if ($unitAlreadyOccupied) {
            return [false, "Unit already occupied", "", 422];
        }

        return DB::transaction(function () use ($tenant, $request) {
            $saved = $tenant->save();

            // Add UserUnit
            if ($request->filled("unitId")) {
                [$saved, $message, $userUnit] = $this->createUserUnit($request, $tenant);
            }

            if ($request->input("sendInvoice")) {
                $request = new Request([
                    "userUnitIds" => [$userUnit->id],
                    "type" => "deposit",
                    "month" => Carbon::parse($request->input("occupiedAt"))->month,
                    "year" => Carbon::parse($request->input("occupiedAt"))->year,
                ]);

                [$saved, $invoiceMessage, $invoice] = (new InvoiceService)->store($request);

                $message = $message." and ".$invoiceMessage;
            }

            return [$saved, $message, $userUnit, 200];
        });
    }

    /*
     * Update Tenant
     */
    public function update($request, $id)
    {
        $tenant = UserUnit::findOrFail($id);

        if ($request->filled("name")) {
            $tenant->user->name = $request->input("name");
        }

        if ($request->filled("email")) {
            $tenant->user->email = $request->input("email");
        }

        if ($request->filled("phone")) {
            $tenant->user->phone = $request->input("phone");
        }

        if ($request->filled("gender")) {
            $tenant->user->gender = $request->input("gender");
        }

        if ($request->filled("password")) {
            $tenant->user->password = Hash::make($request->input("password"));
        }

        if ($request->filled("occupiedAt")) {
            $tenant->user->occupied_at = $request->input("occupiedAt");
        }

        // Mark User Unit as vacated
        if ($request->filled("vacate")) {
            DB::transaction(function () use ($tenant) {
                $tenant->vacated_at = Carbon::now();
                $tenant->save();

                // Set Unit as vacant
                $unit = $tenant->unit;
                $unit->status = "vacant";
                $unit->save();
            });
        }

        $saved = $tenant->user->save();

        $message = $tenant->user->name." Updated Successfully";

        return [$saved, $message, $tenant];
    }

    /*
     * Delete Service
     */
    public function destroy($id)
    {
        $tenant = UserUnit::with("unit", "user")->findOrFail($id);

        // Mark Unit as vacated
        [$deleted, $tenant] = DB::transaction(function () use ($tenant) {
            // Set Unit as vacant
            $unit = $tenant->unit;
            $unit->status = "vacant";
            $unit->save();

            $deleted = $tenant->delete();

            return [$deleted, $tenant];
        });

        return [$deleted, $tenant->user->name." Deleted Successfully", $tenant];
    }

    /*
     * Handle Search
     */
    public function search($query, $request)
    {
        $propertyIds = explode(",", $request->propertyId);

        $isSuper = in_array("All", $propertyIds);

        if (! $isSuper) {
            $query = $query->whereHas("unit.property", function ($query) use ($propertyIds) {
                $query->whereIn("id", $propertyIds);
            });
        }

        $unitId = $request->input("unitId");

        if ($request->filled("unitId")) {
            $query = $query->where("unit_id", $unitId);
        }

        $userUnitId = $request->input("userUnitId");

        if ($request->filled("userUnitId")) {
            $query = $query->where("id", $userUnitId);
        }

        $name = $request->input("name");

        if ($request->filled("name")) {
            $query = $query
                ->whereHas("user", function ($query) use ($name) {
                    $query->where("name", "LIKE", "%".$name."%");
                });
        }

        $phone = $request->input("phone");

        if ($request->filled("phone")) {
            $query = $query
                ->whereHas("user", function ($query) use ($phone) {
                    $query->where("phone", "LIKE", "%".$phone."%");
                });
        }

        $gender = $request->input("gender");

        if ($request->filled("gender")) {
            $query = $query
                ->whereHas("user", function ($query) use ($gender) {
                    $query->where("gender", "LIKE", "%".$gender."%");
                });
        }

        if ($request->filled("vacated")) {
            $query = $query->whereNotNull("vacated_at");
        }

        if ($request->filled("occupied")) {
            $query = $query->whereNull("vacated_at");
        }

        return $query;
    }

    /*
    * Create UserUnit
    */
    public function createUserUnit($request, $tenant)
    {
        $userUnit = new UserUnit;
        $userUnit->user_id = $tenant->id;
        $userUnit->unit_id = $request->input("unitId");
        $userUnit->occupied_at = $request->input("occupiedAt");
        $userUnit->created_by = $this->id;
        $saved = $userUnit->save();

        // Set Unit as occupied
        $unit = $userUnit->unit;
        $unit->status = "occupied";
        $saved = $unit->save();

        return [$saved, $tenant->name." Added Successfully", $userUnit];
    }
}
