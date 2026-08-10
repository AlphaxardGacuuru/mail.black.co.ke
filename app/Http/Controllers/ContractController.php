<?php

namespace App\Http\Controllers;

use App\Http\Resources\ContractResource;
use App\Http\Services\ContractService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ContractController extends Controller
{
    public function __construct(protected ContractService $service)
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
            "userUnitId" => "required|string|exists:user_units,id",
            "type" => "required|string|in:fixed_term,month_to_month,renewal",
            "startDate" => "required|date",
            "endDate" => "nullable|date|after:startDate",
            "rentAmount" => "required|integer",
            "depositAmount" => "nullable|integer",
            "paymentFrequency" => "nullable|string|in:monthly,quarterly,annually",
            "terms" => "nullable|string",
            "status" => "nullable|string|in:active,pending",
            "autoRenew" => "nullable|boolean",
            "noticePeriodDays" => "nullable|integer|min:0",
        ]);

        [$saved, $message, $contract, $statusCode] = $this->service->store($request) + [3 => 200];

        return response([
            "status" => $saved,
            "message" => $message,
            "data" => $contract,
        ], $statusCode);
    }

    /**
     * Display the specified resource.
     */
    public function show(int|string $id): ContractResource
    {
        return $this->service->show($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int|string $id): Response
    {
        $this->validate($request, [
            "type" => "nullable|string|in:fixed_term,month_to_month,renewal",
            "startDate" => "nullable|date",
            "endDate" => "nullable|date",
            "rentAmount" => "nullable|integer",
            "depositAmount" => "nullable|integer",
            "paymentFrequency" => "nullable|string|in:monthly,quarterly,annually",
            "terms" => "nullable|string",
            "status" => "nullable|string|in:active,expired,terminated,pending",
            "autoRenew" => "nullable|boolean",
            "noticePeriodDays" => "nullable|integer|min:0",
            "terminate" => "nullable|boolean",
            "terminationReason" => "nullable|string",
            "sign" => "nullable|boolean",
        ]);

        [$saved, $message, $contract] = $this->service->update($request, $id);

        return response([
            "status" => $saved,
            "message" => $message,
            "data" => $contract,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int|string $id): Response
    {
        [$deleted, $message, $contract] = $this->service->destroy($id);

        return response([
            "status" => $deleted,
            "message" => $message,
            "data" => $contract,
        ], 200);
    }

    public function previewPdf(int|string $id): Response
    {
        $pdf = $this->service->generatePdf($id);

        return $pdf->stream("contract-{$id}-preview.pdf");
    }

    /*
     * Send Contract by Email
     */
    public function sendEmail(int|string $id): Response
    {
        [$sent, $message, $contract] = $this->service->sendEmail($id);

        return response([
            "status" => $sent,
            "message" => $message,
            "data" => $contract,
        ], 200);
    }
}
