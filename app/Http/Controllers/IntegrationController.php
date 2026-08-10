<?php

namespace App\Http\Controllers;

use App\Http\Resources\IntegrationResource;
use App\Http\Services\IntegrationService;
use App\Models\Integration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class IntegrationController extends Controller
{
    public function __construct(protected IntegrationService $service)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        [$status, $message, $integrations] = $this->service->index($request);

        return IntegrationResource::collection($integrations)
            ->additional([
                "status" => $status,
                "message" => $message,
            ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): void
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Integration $integration): void
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Integration $integration): void
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Integration $integration): void
    {
        //
    }
}
