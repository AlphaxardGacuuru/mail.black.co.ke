<?php

namespace App\Http\Services;

use App\Http\Resources\RoleResource;
use App\Models\Role;

class RoleService extends Service
{
    /*
     * Get All Roles
     */
    public function index($request)
    {
        if ($request->filled("idAndName")) {
            $roles = Role::select("uuid as id", "name")
                ->orderBy("name", "DESC")
                ->get();

            return $roles;
        }

        $query = new Role;

        $query = $this->search($query, $request);

        $roles = $query
            ->orderBy("name", "ASC")
            ->paginate(20)
            ->appends($request->all());

        return $roles;
    }

    /*
     * Get Role
     */
    public function show($id)
    {
        $getRole = Role::find($id);

        return new RoleResource($getRole);
    }

    /*
     * Store Role
     */
    public function store($request)
    {
        $role = new Role;
        $role->name = $request->input("name");
        $role->guard_name = "web"; // Use 'web' guard to match auth configuration
        $saved = $role->save();

        $role->syncPermissions($request->input("permissionIds"));

        $message = $role->name . ' Created Successfully!';

        return [$saved, $message, $role];
    }

    /*
     * Update Role
     */
    public function update($request, $id)
    {
        $role = Role::find($id);

        if ($request->filled("name")) {
            $role->name = $request->input("name");
        }

        // Ensure guard_name is set to 'web' to match auth configuration
        $role->guard_name = "web";

        $role->syncPermissions($request->input("permissionIds"));

        $saved = $role->save();

        $message = $role->name . " Updated";

        return [$saved, $message, $role];
    }

    /*
     * Destroy Role
     */
    public function destroy($id)
    {
        $role = Role::find($id);

        $deleted = $role->delete();

        $message = $role->name . " deleted";

        return [$deleted, $message, $role];
    }

    /*
     * Handle Search
     */
    public function search($query, $request)
    {
        $name = $request->input("name");

        if ($request->filled("name")) {
            $query = $query->where("name", "LIKE", "%" . $name . "%");
        }

        return $query;
    }
}
