<?php

namespace App\Http\Controllers;

use App\Http\Resources\RoleResource;
use App\Http\Services\RoleService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class RoleController extends Controller
{
    public function __construct(protected RoleService $service)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $roles = $this->service->index($request);

        return RoleResource::collection($roles);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): Response
    {
        $this->validate($request, [
            "name" => "required|string|unique:roles",
            "description" => "string",
            "permissionIds" => "required|array",
            "permissionIds.*" => "exists:permissions,id",
        ]);

        [$saved, $message, $role] = $this->service->store($request);

        return response([
            "status" => $saved,
            "message" => $message,
            "data" => $role,
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(int|string $id): RoleResource
    {
        return $this->service->show($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int|string $id): Response
    {
        $this->validate($request, [
            "name" => "unique:roles",
        ]);

        [$saved, $message, $role] = $this->service->update($request, $id);

        return response([
            "status" => $saved,
            "message" => $message,
            "data" => $role,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int|string $id): Response
    {
        [$deleted, $message, $role] = $this->service->destroy($id);

        return response([
            "status" => $deleted,
            "message" => $message,
            "data" => $role,
        ], 200);
    }
}
