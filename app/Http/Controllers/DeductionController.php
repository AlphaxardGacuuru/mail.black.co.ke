<?php

namespace App\Http\Controllers;

use App\Http\Resources\DeductionResource;
use App\Http\Services\DeductionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class DeductionController extends Controller
{
    public function __construct(protected DeductionService $service)
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
            "userUnitIds" => "required|array",
            "description" => "required|string",
            "amount" => "required|integer",
            "month" => "required|integer|min:1",
            "year" => "required|integer",
            "month" => "nullable|integer|min:1",
            "year" => "nullable|integer",
        ]);

        [$saved, $message, $deductions] = $this->service->store($request);

        return response([
            "status" => $saved,
            "message" => $message,
            "data" => $deductions,
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(int|string $id): DeductionResource
    {
        return $this->service->show($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int|string $id): Response
    {
        $this->validate($request, [
            "description" => "nullable|string",
            "amount" => "nullable|integer",
        ]);

        [$saved, $message, $deductions] = $this->service->update($request, $id);

        return response([
            "status" => $saved,
            "message" => $message,
            "data" => $deductions,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int|string $id): Response
    {
        [$deleted, $message, $deduction] = $this->service->destroy($id);

        return response([
            "status" => $deleted,
            "message" => $message,
            "data" => $deduction,
        ], 200);
    }
}
