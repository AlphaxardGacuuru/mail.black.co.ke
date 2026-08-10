<?php

namespace App\Http\Controllers;

use App\Http\Resources\PaymentResource;
use App\Http\Services\PaymentService;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PaymentController extends Controller
{
    public function __construct(protected PaymentService $service)
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
            "channel" => "nullable|string",
            "amount" => "required|string|min:1",
            "transactionReference" => "nullable|string",
            "month" => "required|integer|min:1",
            "year" => "required|integer",
        ]);

        [$saved, $message, $payment] = $this->service->store($request);

        return response([
            "status" => $saved,
            "message" => $message,
            "data" => $payment,
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(int|string $id): PaymentResource
    {
        return $this->service->show($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int|string $id): Response
    {
        $this->validate($request, [
            "userId" => "nullable|string",
            "channel" => "nullable|string",
            "amount" => "nullable|string|min:1",
            "transactionReference" => "nullable|string",
            "month" => "nullable|integer|min:1",
            "year" => "nullable|integer",
        ]);

        [$saved, $message, $payment] = $this->service->update($request, $id);

        return response([
            "status" => $saved,
            "message" => $message,
            "data" => $payment,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int|string $id): Response
    {
        [$deleted, $message, $payment] = $this->service->destroy($id);

        return response([
            "status" => $deleted,
            "message" => $message,
            "data" => $payment,
        ], 200);
    }

    public function previewPdf(int|string $id): Response
    {
        $pdf = $this->service->generatePdf($id);

        return $pdf->stream("payment-{$id}-preview.pdf");
    }

    /*
     * Send Payment by Email
     */
    public function sendEmail(int|string $id): Response
    {
        [$sent, $message, $payment] = $this->service->sendEmail($id);

        return response([
            "status" => $sent,
            "message" => $message,
            "data" => $payment,
        ], 200);
    }
}
