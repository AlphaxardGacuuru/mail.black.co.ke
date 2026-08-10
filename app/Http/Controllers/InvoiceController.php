<?php

namespace App\Http\Controllers;

use App\Http\Resources\InvoiceResource;
use App\Http\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class InvoiceController extends Controller
{
    public function __construct(protected InvoiceService $service)
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
            "userUnitIds" => "nullable|array",
            "type" => "required|string",
            "month" => "required|integer|min:1",
            "year" => "required|integer",
        ]);

        [$saved, $message, $invoices] = $this->service->store($request);

        return response([
            "status" => $saved,
            "message" => $message,
            "data" => $invoices,
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(int|string $id): InvoiceResource
    {
        return $this->service->show($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int|string $id): Response
    {
        $this->validate($request, [
            "userUnitIds" => "nullable|array",
            "type" => "nullable|string",
            "month" => "nullable|integer",
            "year" => "nullable|integer",
        ]);

        [$saved, $message, $invoices] = $this->service->update($request, $id);

        return response([
            "status" => $saved,
            "message" => $message,
            "data" => $invoices,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int|string $id): Response
    {
        [$deleted, $message, $invoice] = $this->service->destroy($id);

        return response([
            "status" => $deleted,
            "message" => $message,
            "data" => $invoice,
        ], 200);
    }

    public function previewPdf(int|string $id): Response
    {
        $pdf = $this->service->generatePdf($id);

        return $pdf->stream("invoice-{$id}-preview.pdf");
    }

    /*
     * Send Invoices by Email
     */
    public function sendEmail(int|string $id): Response
    {
        [$sent, $message, $invoice] = $this->service->sendEmail($id);

        return response([
            "status" => $sent,
            "message" => $message,
            "data" => $invoice,
        ], 200);
    }

    /*
     * Send Invoice by SMS
     */
    public function sendSMS(int|string $id): Response
    {
        [$sent, $message, $invoice] = $this->service->sendSMS($id);

        return response([
            "status" => $sent,
            "message" => $message,
            "data" => $invoice,
        ], 200);
    }
}
