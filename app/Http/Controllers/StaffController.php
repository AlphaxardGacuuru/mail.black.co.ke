<?php

namespace App\Http\Controllers;

use App\Http\Resources\StaffResource;
use App\Http\Services\StaffService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class StaffController extends Controller
{
    public function __construct(protected StaffService $service)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        [$status, $message, $staff] = $this->service->index($request);

        return StaffResource::collection($staff)
            ->additional([
                "status" => $status,
                "message" => $message,
            ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): Response
    {
        $this->validate($request, [
            "name" => "required|string",
            "email" => "required|email",
            "phone" => "required|digits:10|unique:users,phone",
            "gender" => "required|string",
            "propertyId" => "required|string",
        ]);

        [$saved, $message, $staff, $code] = $this->service->store($request);

        $title = $saved ? "message" : "errors";
        $message = $saved ? $message : [$message];

        return response([
            "status" => $saved,
            $title => $message,
            "data" => $staff,
        ], $code);
    }

    /**
     * Display the specified resource.
     */
    public function show(int|string $id): StaffResource
    {
        return $this->service->show($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int|string $id): Response
    {
        $this->validate($request, [
            "name" => "nullable|string",
            "email" => "nullable|email|unique:users",
            "phone" => "nullable|digits:10|unique:users,phone,".$id,
            "gender" => "nullable|string",
            "propertyId" => "nullable|string",
        ]);

        [$saved, $message, $staff] = $this->service->update($request, $id);

        return response([
            "status" => $saved,
            "message" => $message,
            "data" => $staff,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, int|string $id): Response
    {
        [$deleted, $message, $staff] = $this->service->destroy($request, $id);

        return response([
            "status" => $deleted,
            "message" => $message,
            "data" => $staff,
        ], 200);
    }

    /*
     * Get Units by Property ID
     */
    public function byPropertyId(int|string $id): mixed
    {
        return $this->service->byPropertyId($id);
    }
}
