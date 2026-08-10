<?php

namespace App\Http\Controllers;

use App\Http\Resources\WaterReadingResource;
use App\Http\Services\WaterReadingService;
use App\Models\WaterReading;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class WaterReadingController extends Controller
{
    public function __construct(protected WaterReadingService $service)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        [$waterReadings, $totalUsage, $totalBill] = $this->service->index($request);

        return WaterReadingResource::collection($waterReadings)
            ->additional([
                "totalUsage" => number_format($totalUsage),
                "totalBill" => number_format($totalBill),
                "unitId" => $request->unitId,
            ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): Response
    {
        $this->validate($request, [
            "waterReadings" => "required|array",
            "type" => "required|string",
            "month" => "required|integer",
            "year" => "required|integer",
        ]);

        [$saved, $message, $waterReadings] = $this->service->store($request);

        return response([
            "status" => $saved,
            "message" => $message,
            "data" => $waterReadings,
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(int|string $id): WaterReadingResource
    {
        return $this->service->show($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int|string $id): Response
    {
        $this->validate($request, [
            "type" => "nullable|string",
            "reading" => "nullable|integer",
            "month" => "nullable|integer",
            "year" => "nullable|integer",
        ]);

        [$saved, $message, $waterReading] = $this->service->update($request, $id);

        return response([
            "status" => $saved,
            "message" => $message,
            "data" => $waterReading,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int|string $id): Response
    {
        [$deleted, $message, $waterReading] = $this->service->destroy($id);

        return response([
            "status" => $deleted,
            "message" => $message,
            "data" => $waterReading,
        ], 200);
    }

    /*
     * Get Water Readings by Property ID
     */
    public function byPropertyId(Request $request, int|string $id): mixed
    {
        return $this->service->byPropertyId($request, $id);
    }
}
