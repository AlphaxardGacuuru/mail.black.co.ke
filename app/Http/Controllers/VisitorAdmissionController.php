<?php

namespace App\Http\Controllers;

use App\Http\Resources\VisitorAdmissionResource;
use App\Http\Services\VisitorAdmissionService;
use App\Models\VisitorAdmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class VisitorAdmissionController extends Controller
{
    public function __construct(protected VisitorAdmissionService $service)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $visitorAdmissions = $this->service->index($request);

        return VisitorAdmissionResource::collection($visitorAdmissions)
            ->additional([
                "status" => 200,
                "message" => $visitorAdmissions->count()." Visitor Admissions Fetched Successfully",
            ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): void
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $this->validate($request, [
            "tenantId" => "required|exists:user_units,id",
            "firstName" => "required|string|max:255",
            "middleName" => "nullable|string|max:255",
            "lastName" => "required|string|max:255",
            "nationalID" => "required|string|max:255",
            "email" => "required|email",
            "phone" => "required|string|max:255",
            "propertyId" => "required|exists:properties,id",
        ]);

        [$saved, $message, $data] = $this->service->store($request);

        return response()->json([
            "status" => $saved,
            "message" => $message,
            "data" => $data,
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(int|string $id): JsonResponse
    {
        $visitorAdmission = $this->service->show($id);

        return response()->json([
            "status" => true,
            "message" => "Visitor Admission Retrieved Successfully",
            "data" => new VisitorAdmissionResource($visitorAdmission),
        ], 200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int|string $id): void {}

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int|string $id): Response
    {
        $this->validate($request, [
            "tenantId" => "nullable|exists:user_units,id",
            "iprsRecordId" => "nullable|exists:iprs_records,id",
            "firstName" => "nullable|string|max:255",
            "middleName" => "nullable|string|max:255",
            "lastName" => "nullable|string|max:255",
            "nationalID" => "nullable|string|max:255",
            "email" => "nullable|email",
            "phone" => "nullable|string|max:255",
            "propertyId" => "nullable|exists:properties,id",
        ]);

        [$saved, $message, $visitorAdmission] = $this->service->update($request, $id);

        return response([
            "status" => $saved,
            "message" => $message,
            "data" => new VisitorAdmissionResource($visitorAdmission),
        ], $saved ? 200 : 400);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int|string $id): Response
    {
        [$deleted, $message, $visitorAdmission] = $this->service->destroy($id);

        return response([
            "status" => $deleted,
            "message" => $message,
            "data" => $visitorAdmission,
        ], 200);
    }
}
