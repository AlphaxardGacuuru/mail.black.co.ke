<?php

namespace App\Http\Services;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class UserService extends Service
{
    /*
     * Get All Users
     */
    public function index($request)
    {
        if ($request->filled("idAndName")) {
            $userQuery = User::select("id", "name");

            $users = $userQuery
                ->orderBy("id", "DESC")
                ->get();

            return $users;
        }

        $query = new User;

        $query = $this->search($query, $request);

        $users = $query
            ->orderby("id", "DESC")
            ->paginate();

        return $users;
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        return User::findOrFail($id);
    }

    /**
     * Create a new user (registration logic)
     *
     * @throws ValidationException
     */
    public function store($request)
    {
        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => $request->input('password'),
        ]);

        return $user;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update($request, $id)
    {
        $user = User::findOrFail($id);
        $user->name = $request->filled('name') ? $request->input('name') : $user->name;
        $user->phone = $request->filled('phone') ? $request->input('phone') : $user->phone;

        // Password update handled if present (already validated in controller)
        if ($request->filled('password')) {
            $user->password = Hash::make($request->input('password'));
        }

        $user->settings = $request->filled('settings') ? $request->input('settings') : $user->settings;

        if ($request->filled('userRoles')) {
            $user->syncRoles($request->userRoles);
        }

        $saved = $user->save();

        return [$saved, "Account Updated", $user];
    }

    /*
     * Soft Delete Service
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        $deleted = $user->delete();

        return [$deleted, $user->name . " deleted"];
    }

    /*
     * Force Delete Service
     */
    public function forceDestroy($id)
    {
        $user = User::findOrFail($id);

        // Get old thumbnail and delete it
        $oldThumbnail = substr($user->thumbnail, 9);

        Storage::disk("public")->delete($oldThumbnail);

        $deleted = $user->delete();

        return [$deleted, $user->name . " deleted"];
    }

    /**
     * Get Auth.
     */
    public function auth()
    {
        if (auth("sanctum")->check()) {

            $auth = auth('sanctum')->user();

            return new UserResource($auth);
        } else {
            return response(["message" => "Not Authenticated"], 401);
        }
    }

    /*
     * Search
     */
    public function search($query, $request)
    {
        $roleId = $request->roleId;

        if ($request->filled("roleId")) {
            $roleName = Role::find($roleId)->name;

            $query = $query->role($roleName);
        }

        $name = $request->input("name");

        if ($request->filled("name")) {
            $query = $query->where("name", "LIKE", "%" . $name . "%");
        }

        return $query;
    }
}
