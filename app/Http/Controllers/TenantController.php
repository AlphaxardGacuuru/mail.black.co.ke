<?php

namespace App\Http\Controllers;

use App\Http\Resources\TenantResource;
use App\Http\Services\TenantService;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TenantController extends Controller
{
    public function __construct(protected TenantService $service)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        [$status, $message, $tenants] = $this->service->index($request);

        return TenantResource::collection($tenants)
            ->additional([
                'status' => $status,
                'message' => $message,
            ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $this->validate($request, [
            "name" => "required|string",
            "email" => "required|string",
            "phone" => "required|digits:10",
            "occupiedAt" => "required|date",
            "sendInvoice" => "required|boolean",
        ]);

        [$saved, $message, $tenant, $code] = $this->service->store($request);

        $title = $saved ? "message" : "errors";
        $message = $saved ? $message : [$message];

        return TenantResource::make($tenant)
            ->additional([
                'status' => $saved,
                $title => $message,
            ])
            ->response()
            ->setStatusCode($code);
    }

    /**
     * Display the specified resource.
     */
    public function show(int|string $id): TenantResource
    {
        [$status, $message, $tenant] = $this->service->show($id);

        return TenantResource::make($tenant)
            ->additional([
                'status' => $status,
                'message' => $message,
            ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int|string $id): TenantResource
    {
        $this->validate($request, [
            "name" => "nullable|string",
            "email" => "nullable|string",
            "phone" => "nullable|digits:10|unique:users,phone,".$id,
            "occupiedAt" => "nullable|date",
        ]);

        [$saved, $message, $tenant] = $this->service->update($request, $id);

        return TenantResource::make($tenant)
            ->additional([
                'status' => $saved,
                'message' => $message,
            ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int|string $id): TenantResource
    {
        [$deleted, $message, $tenant] = $this->service->destroy($id);

        return (new TenantResource($tenant))
            ->additional([
                'status' => $deleted,
                'message' => $message,
            ]);
    }
}
