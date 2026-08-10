<?php

namespace App\Http\Controllers;

use App\Http\Resources\LeadResource;
use App\Http\Services\LeadService;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class LeadController extends Controller
{
    public function __construct(protected LeadService $service)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $lead = $this->service->index($request);

        return LeadResource::collection($lead);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): LeadResource
    {
        $this->validate($request, [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'type' => 'nullable|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'address' => 'nullable|string|max:255',
            'outcome' => 'required|string|max:255',
        ]);

        [$saved, $message, $lead] = $this->service->store($request);

        return (new LeadResource($lead))->additional([
            'status' => $saved,
            'message' => $message,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(int|string $id): LeadResource
    {
        $lead = $this->service->show($id);

        return new LeadResource($lead);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int|string $id): LeadResource
    {
        $this->validate($request, [
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'type' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'address' => 'nullable|string|max:255',
            'outcome' => 'nullable|string|max:255',
        ]);

        [$saved, $message, $lead] = $this->service->update($request, $id);

        return (new LeadResource($lead))->additional([
            'status' => $saved,
            'message' => $message,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int|string $id): Response
    {
        [$saved, $message, $lead] = $this->service->destroy($id);

        return response([
            'status' => $saved,
            'message' => $message,
            'data' => $lead,
        ]);
    }
}
