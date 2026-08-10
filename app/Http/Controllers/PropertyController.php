<?php

namespace App\Http\Controllers;

use App\Http\Resources\PropertyResource;
use App\Http\Services\PropertyService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class PropertyController extends Controller
{
    public function __construct(protected PropertyService $service)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response|AnonymousResourceCollection
    {
        return $this->service->index($request);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): Response
    {
        $this->validate($request, [
            "name" => "required|string",
            "location" => "required|string",
            "depositFormula" => "required|string",
            "serviceCharge" => "nullable|array",
            "waterBillRate" => "required|array",
            "invoiceDate" => "required|integer",
            "invoiceReminderDuration" => "required|integer",
            "contractTerms" => "nullable|string",
            "email" => "required_without:sms|boolean",
            "sms" => "required_without:email|boolean",
        ]);

        // Ensure at least one of email or sms is true
        if (! $request->email && ! $request->sms) {
            // Throw validation error if both email and sms are false
            throw ValidationException::withMessages([
                "email|sms" => ["At least one of email or sms must be set."],
            ]);
        }

        [$saved, $message, $property] = $this->service->store($request);

        return response([
            "status" => $saved,
            "message" => $message,
            "data" => $property,
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(int|string $id): PropertyResource
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
            "location" => "nullable|string",
            "depositFormula" => "nullable|string",
            "serviceCharge" => "nullable|array",
            "waterBillRate" => "nullable|array",
            "invoiceDate" => "nullable|integer",
            "invoiceReminderDuration" => "nullable|integer",
            "contractTerms" => "nullable|string",
            "email" => "required_without:sms|boolean",
            "sms" => "required_without:email|boolean",
        ]);

        // Ensure at least one of email or sms is true
        if (! $request->email && ! $request->sms) {
            // Throw validation error if both email and sms are false
            throw ValidationException::withMessages([
                "email|sms" => ["At least one of email or sms must be set."],
            ]);
        }

        [$saved, $message, $property] = $this->service->update($request, $id);

        return response([
            "status" => $saved,
            "message" => $message,
            "data" => $property,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int|string $id): Response
    {
        [$deleted, $message, $property] = $this->service->destroy($id);

        return response([
            "status" => $deleted,
            "message" => $message,
            "data" => $property,
        ], 200);
    }

    /**
     * Remove multiple resources from storage.
     */
    public function destroyMany(Request $request): Response
    {
        $this->validate($request, [
            "ids" => "required|array|min:1",
            "ids.*" => "string",
        ]);

        [$deletedCount, $message] = $this->service->destroyMany($request->input("ids"));

        return response([
            "status" => $deletedCount > 0,
            "message" => $message,
            "data" => ["deletedCount" => $deletedCount],
        ], 200);
    }

    /*
     * Dashboard
     */
    public function dashboard(Request $request): mixed
    {
        return $this->service->dashboard($request);
    }
}
