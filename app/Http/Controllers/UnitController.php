<?php

namespace App\Http\Controllers;

use App\Http\Resources\UnitResource;
use App\Http\Services\UnitService;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class UnitController extends Controller
{
    public function __construct(protected UnitService $service)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return $this->service->index($request);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): Response
    {
        $this->validate($request, [
            "propertyId" => "required|string",
            "name" => "required|string",
            "rent" => "required|string",
            "deposit" => "required|string",
            "serviceCharge" => "nullable|array",
            "type" => "required|string",
            "bedrooms" => "nullable|string",
            "size" => "nullable|array",
            "ensuite" => "required|integer",
            "dsq" => "required|boolean",
        ]);

        [$saved, $message, $unit] = $this->service->store($request);

        return response([
            "status" => $saved,
            "message" => $message,
            "data" => $unit,
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(int|string $id): UnitResource
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
            "rent" => "nullable|string",
            "deposit" => "nullable|string",
            "serviceCharge" => "nullable|array",
            "type" => "nullable|string",
            "bedrooms" => "nullable|string",
            "size" => "nullable|array",
            "ensuite" => "nullable|integer",
            "dsq" => "nullable|boolean",
        ]);

        [$saved, $message, $unit] = $this->service->update($request, $id);

        return response([
            "status" => $saved,
            "message" => $message,
            "data" => $unit,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int|string $id): Response
    {
        [$deleted, $message, $unit] = $this->service->destroy($id);

        return response([
            "status" => $deleted,
            "message" => $message,
            "data" => $unit,
        ], 200);
    }

    /*
    * Get Units by Property ID
    */
    public function byPropertyId(Request $request, int|string $id): mixed
    {
        return $this->service->byPropertyId($request, $id);
    }
}
