<?php

namespace App\Http\Services;

use App\Models\User;
use App\Models\UserProperty;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class OwnerService extends Service
{
    /*
     * Get All Owner
     */
    public function index($request)
    {
        $query = UserProperty::where("type", "owner");

        $query = $this->search($query, $request);

        $query = $query->orderBy("id", "DESC");

        if ($request->filled("idAndName")) {
            $owners = $query
                ->get();
        } else {
            $owners = $query
                ->paginate(20)
                ->appends($request->all());
        }

        return [true, $owners->count() . " Owners Retrieved Successfully.", $owners];
    }

    /*
     * Get One Owner
     */
    public function show($id)
    {
        $owner = UserProperty::findOrFail($id);

        return [true, "Owner Retrieved Successfully.", $owner];
    }

    /*
     * Store
     */
    public function store($request)
    {
        $ownerQuery = User::where("email", $request->email);

        // Check if User exists
        $doesntExist = $ownerQuery->doesntExist();

        if ($doesntExist) {
            $owner = new User;
            $owner->name = $request->input("name");
            $owner->email = $request->input("email");
            $owner->phone = $request->input("phone");
            $owner->gender = $request->input("gender");
            $owner->password = Hash::make($request->input("email"));
        } else {
            $owner = $ownerQuery->first();

            // Check if owner Already Exists
            $ownerExists = UserProperty::where("user_id", $owner->id)
                ->where("property_id", $request->propertyId)
                ->exists();

            if ($ownerExists) {
                return [false, "Owner Already Exists", "", 422];
            }
        }

        $saved = DB::transaction(function() use ($request, $owner) {
            $saved = $owner->save();

            $userProperty = new UserProperty;
            $userProperty->user_id = $owner->id;
            $userProperty->property_id = $request->propertyId;
            $userProperty->type = "owner";
            $userProperty->save();

            return $saved;
        });

        $message = $owner->name . " Added Successfully";

        return [$saved, $message, $owner, 200];
    }

    /*
     * Update Owner
     */
    public function update($request, $id)
    {
        $owner = UserProperty::findOrFail($id);

        if ($request->filled("name")) {
            $owner->user->name = $request->input("name");
        }

        if ($request->filled("email")) {
            $owner->user->email = $request->input("email");
        }

        if ($request->filled("phone")) {
            $owner->user->phone = $request->input("phone");
        }

        if ($request->filled("gender")) {
            $owner->user->gender = $request->input("gender");
        }

        if ($request->filled("password")) {
            $owner->user->password = Hash::make($request->input("email"));
        }

        if ($request->filled("propertyId")) {
            DB::transaction(function() use ($owner, $request) {
                // Remove properties not included
                UserProperty::where("user_id", $owner->user->id)
                    ->delete();

                $userProperty = new UserProperty;
                $userProperty->user_id = $owner->user->id;
                $userProperty->property_id = $request->propertyId;
                $userProperty->type = "owner";
                $userProperty->save();
            });
        }

        $saved = $owner->user->save();
        $saved = $owner->save();

        $message = $owner->user->name . " Updated Successfully";

        return [$saved, $message, $owner];
    }

    /*
     * Soft Delete Service
     */
    public function destroy($id)
    {
        $owner = UserProperty::findOrFail($id);

        $deleted = $owner->delete();

        return [$deleted, $owner->user->name . " Deleted Successfully", $owner];
    }

    /*
     * Search
     */
    public function search($query, $request)
    {
        $propertyId = explode(",", $request->propertyId);

        $query = $query->whereIn("property_id", $propertyId);

        if ($request->filled("idAndName")) {
            $query = $query->select("id", "user_id")->with("user:id,name");
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
