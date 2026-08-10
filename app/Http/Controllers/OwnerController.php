<?php

namespace App\Http\Controllers;

use App\Http\Resources\OwnerResource;
use App\Http\Services\OwnerService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class OwnerController extends Controller
{
    public function __construct(protected OwnerService $service)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        [$status, $message, $owners] = $this->service->index($request);

        return OwnerResource::collection($owners)
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
    public function show(int|string $id): OwnerResource
    {
        [$status, $message, $owner] = $this->service->show($id);

        return (new OwnerResource($owner))->additional([
            "status" => $status,
            "message" => $message,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int|string $id): OwnerResource
    {
        $this->validate($request, [
            "name" => "sometimes|string",
            "email" => "sometimes|email",
            "phone" => "sometimes|digits:10|unique:users,phone,".$id,
            "gender" => "sometimes|string",
            "propertyId" => "sometimes|string",
        ]);

        [$updated, $message, $owner] = $this->service->update($request, $id);

        return (new OwnerResource($owner))->additional([
            "status" => $updated,
            "message" => $message,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int|string $id): OwnerResource
    {
        [$deleted, $message, $owner] = $this->service->destroy($id);

        return (new OwnerResource($owner))->additional([
            "status" => $deleted,
            "message" => $message,
        ]);
    }
}
