<?php

namespace App\Http\Controllers;

use App\Http\Resources\StageResource;
use App\Http\Services\StageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StageController extends Controller
{
    public function __construct(protected StageService $service)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        [$status, $message, $stages] = $this->service->index($request);

        return StageResource::collection($stages)
            ->additional([
                "status" => $status,
                "message" => $message,
            ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): StageResource
    {
        $this->validate($request, [
            "name" => "required|string",
        ]);

        [$saved, $message, $stage] = $this->service->store($request);

        return (new StageResource($stage))
            ->additional([
                "status" => $saved,
                "message" => $message,
            ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(int|string $id): StageResource
    {
        [$status, $message, $stage] = $this->service->show($id);

        return (new StageResource($stage))
            ->additional([
                "status" => $status,
                "message" => $message,
            ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int|string $id): StageResource
    {
        $this->validate($request, [
            "name" => "nullable|string",
        ]);

        [$saved, $message, $stage] = $this->service->update($request, $id);

        return (new StageResource($stage))
            ->additional([
                "status" => $saved,
                "message" => $message,
            ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int|string $id): StageResource
    {
        [$deleted, $message, $stage] = $this->service->destroy($id);

        return (new StageResource($stage))
            ->additional([
                "status" => $deleted,
                "message" => $message,
            ]);
    }
}
