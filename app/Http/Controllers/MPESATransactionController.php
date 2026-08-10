<?php

namespace App\Http\Controllers;

use App\Events\MpesaTransactionCreatedEvent;
use App\Http\Services\MPESATransactionService;
use App\Models\MPESATransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class MPESATransactionController extends Controller
{
    public function __construct(protected MPESATransactionService $service)
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
        [$saved, $message, $mpesaTransaction] = $this->service->store($request);

        MpesaTransactionCreatedEvent::dispatchIf($saved, $mpesaTransaction);

        return response([
            "status" => $saved ? "success" : "failed",
            "message" => $message,
            "data" => $mpesaTransaction,
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(MPESATransaction $mPESATransaction): void
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MPESATransaction $mPESATransaction): void
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MPESATransaction $mPESATransaction): void
    {
        //
    }

    /**
     * Send STK Push to Kopokopo.
     */
    public function stkPush(Request $request): Response
    {
        [$status, $message, $data] = $this->service->stkPush($request);

        return response([
            "status" => $status,
            "message" => $message,
            "data" => $data,
        ], 200);
    }

    /**
     * Check STK Push Status with Kopokopo.
     */
    public function stkPushStatus(Request $request): Response
    {
        [$status, $message, $data] = $this->service->stkPushStatus($request);

        return response([
            "status" => $status,
            "message" => $message,
            "data" => $data,
        ], 200);
    }

    public function saveWebhook(Request $request): Response
    {
        [$status, $message, $data] = $this->service->saveWebhook($request);

        return response([
            "status" => $status,
            "message" => $message,
            "data" => $data,
        ], 200);
    }
}
