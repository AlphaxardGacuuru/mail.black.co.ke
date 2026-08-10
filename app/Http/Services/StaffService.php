<?php

namespace App\Http\Services;

use App\Http\Resources\StaffResource;
use App\Models\User;
use App\Models\UserProperty;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class StaffService extends Service
{
    /*
     * Get All Staff
     */
    public function index($request)
    {
        $query = UserProperty::where("type", "staff");

        $query = $this->search($query, $request);

        $query = $query->orderBy("id", "DESC");

        if ($request->filled("idAndName")) {
            $staff = $query
                ->get();
        } else {
            $staff = $query
                ->paginate(20)
                ->appends($request->all());
        }

        return [true, $staff->count() . " Staff Retrieved Successfully.", $staff];
    }

    /*
     * Get One Staff
     */
    public function show($id)
    {
        $staff = UserProperty::findOrFail($id);

        return new StaffResource($staff);
    }

    /*
     * Store
     */
    public function store($request)
    {
        $staffQuery = User::where("email", $request->email);

        // Check if User exists
        $doesntExist = $staffQuery->doesntExist();

        if ($doesntExist) {
            $staff = new User;
            $staff->name = $request->input("name");
            $staff->email = $request->input("email");
            $staff->phone = $request->input("phone");
            $staff->gender = $request->input("gender");
            $staff->password = Hash::make($request->input("email"));
        } else {
            $staff = $staffQuery->first();

            // Check if staff Already Exists
            $staffExists = UserProperty::where("user_id", $staff->id)
                ->where("property_id", $request->propertyId)
                ->exists();

            if ($staffExists) {
                return [false, "Staff Already Exists", "", 422];
            }
        }

        $saved = DB::transaction(function() use ($request, $staff) {
            $saved = $staff->save();

            $userProperty = new UserProperty;
            $userProperty->user_id = $staff->id;
            $userProperty->property_id = $request->propertyId;
            $userProperty->type = "staff";
            $userProperty->save();

            $userProperty->syncRoles($request->userRoles);

            return $saved;
        });

        $message = $staff->name . " Added Successfully";

        return [$saved, $message, $staff, 200];
    }

    /*
     * Update Staff
     */
    public function update($request, $id)
    {
        $staff = UserProperty::findOrFail($id);

        if ($request->filled("name")) {
            $staff->user->name = $request->input("name");
        }

        if ($request->filled("email")) {
            $staff->user->email = $request->input("email");
        }

        if ($request->filled("phone")) {
            $staff->user->phone = $request->input("phone");
        }

        if ($request->filled("gender")) {
            $staff->user->gender = $request->input("gender");
        }

        if ($request->filled("password")) {
            $staff->user->password = Hash::make($request->input("email"));
        }

        if ($request->filled("propertyId")) {
            DB::transaction(function() use ($staff, $request) {
                // Remove propertys not included
                UserProperty::where("user_id", $staff->user->id)
                    ->delete();

                $userProperty = new UserProperty;
                $userProperty->user_id = $staff->user->id;
                $userProperty->property_id = $request->propertyId;
                $userProperty->type = "staff";
                $userProperty->save();
            });
        }

        if ($request->filled("userRoles")) {
            $staff->syncRoles($request->userRoles);
        }

        $saved = $staff->user->save();
        $saved = $staff->save();

        $message = $staff->user->name . " Updated Successfully";

        return [$saved, $message, $staff];
    }

    /*
     * Soft Delete Service
     */
    public function destroy($request, $id)
    {
        $staff = UserProperty::findOrFail($id);

        $deleted = $staff->delete();

        return [$deleted, $staff->user->name . " Deleted Successfully", $staff];
    }

    /*
     * Search
     */
    public function search($query, $request)
    {
        $propertyId = explode(",", $request->propertyId);

        $query = $query->whereIn("property_id", $propertyId);

        if ($request->filled("idAndName")) {
            $query = $query->select("id", "user_id", "property_id")->with("user:id,name");
        }

        $roleId = $request->roleId;

        if ($request->filled("roleId")) {
            $roleName = Role::find($roleId)->name;

            $query = $query->role($roleName);
        }

        if ($request->filled("userId")) {
            $query = $query->where("user_id", $request->userId);
        }

        $name = $request->input("name");

        if ($request->filled("name")) {
            $query = $query
                ->whereHas("user", function($query) use ($name) {
                    $query->where("name", "LIKE", "%" . $name . "%");
                });
        }

        return $query;
    }
}
