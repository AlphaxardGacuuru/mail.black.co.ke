<?php

namespace App\Http\Services;

use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Notifications\PaymentNotification;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\HttpTransportException;

class PaymentService extends Service
{
    /*
     * Fetch All Payments
     */
    public function index($request)
    {
        $paymentQuery = new Payment;

        $paymentQuery = $this->search($paymentQuery, $request);

        $sum = $paymentQuery->sum("amount");

        $payments = $paymentQuery
            ->orderBy("month", "DESC")
            ->orderBy("year", "DESC")
            ->orderBy("id", "DESC")
            ->paginate(20)
            ->appends([
                "propertyId" => $request->propertyId,
                "unitId" => $request->unitId,
            ]);

        return PaymentResource::collection($payments)
            ->additional(["sum" => number_format($sum)]);
    }

    /*
     * Display the specified resource.
     */
    public function show($id)
    {
        $payment = Payment::findOrFail($id);

        return new PaymentResource($payment);
    }

    /*
     * Store a newly created resource in storage.
     */
    public function store($request)
    {
        foreach ($request->userUnitIds as $userUnitId) {
            $payment = new Payment;
            $payment->user_unit_id = $userUnitId;
            $payment->amount = $request->amount;
            $payment->transaction_reference = $request->transactionReference;
            $payment->channel = $request->channel;
            $payment->month = $request->month;
            $payment->year = $request->year;
            $payment->created_by = $this->id;

            $saved = DB::transaction(function () use ($payment) {
                $saved = $payment->save();

                // Update Invoice Status
                $this->updateInvoiceStatus($payment->user_unit_id);

                return $saved;
            });
        }

        $message = "Payment Added Successfully";

        return [$saved, $message, $payment];
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update($request, $id)
    {
        $payment = Payment::findOrFail($id);

        if ($request->filled("amount")) {
            $payment->amount = $request->input("amount");
        }

        if ($request->filled("transactionReference")) {
            $payment->transaction_reference = $request->input("transactionReference");
        }

        if ($request->filled("channel")) {
            $payment->channel = $request->input("channel");
        }

        if ($request->filled("month")) {
            $payment->month = $request->month;
        }

        if ($request->filled("year")) {
            $payment->year = $request->year;
        }

        $saved = DB::transaction(function () use ($payment) {
            $saved = $payment->save();

            // Update Invoice Status
            $this->updateInvoiceStatus($payment->user_unit_id);

            return $saved;
        });

        $message = "Payment Updated Successfully";

        return [$saved, $message, $payment];
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        $payment = Payment::findOrFail($id);

        $deleted = DB::transaction(function () use ($payment) {
            $deleted = $payment->delete();

            // Update Invoice Status
            $this->updateInvoiceStatus($payment->user_unit_id);

            return $deleted;
        });

        $message = "Payment Deleted Successfully";

        return [$deleted, $message, $payment];
    }

    /*
     * Handle Search
     */
    public function search($query, $request)
    {
        if ($request->propertyId != "undefined") {
            $propertyIds = explode(",", $request->propertyId);

            $isSuper = in_array("All", $propertyIds);

            if (! $isSuper) {
                $query = $query->whereHas("userUnit.unit.property", function ($query) use ($propertyIds) {
                    $query->whereIn("id", $propertyIds);
                });
            }
        }

        if ($request->filled("unitId") && $request->unitId != "undefined") {
            $unitId = $request->input("unitId");

            $query = $query->whereHas("userUnit.unit", function ($query) use ($unitId) {
                $query->where("id", $unitId);
            });
        }

        $unit = $request->input("unit");

        if ($request->filled("unit")) {
            $query = $query
                ->whereHas("userUnit.unit", function ($query) use ($unit) {
                    $query->where("name", "LIKE", "%".$unit."%");
                });
        }

        $tenant = $request->input("tenant");

        if ($request->filled("tenant")) {
            $query = $query
                ->whereHas("userUnit.user", function ($query) use ($tenant) {
                    $query->where("name", "LIKE", "%".$tenant."%");
                });
        }

        if ($request->filled("tenantId") && $request->tenantId != "undefined") {
            $tenantId = $request->input("tenantId");

            $query = $query->whereHas("userUnit", function ($query) use ($tenantId) {
                $query->where("user_id", $tenantId);
            });
        }

        $userUnitId = $request->input("userUnitId");

        if ($request->filled("userUnitId")) {
            $query = $query->where("user_unit_id", $userUnitId);
        }

        $month = $request->input("month");

        if ($request->filled("month")) {
            $query = $query->where("month", $month);
        }

        $year = $request->input("year");

        if ($request->filled("year")) {
            $query = $query->where("year", $year);
        }

        $startMonth = $request->filled("startMonth") ? $request->input("startMonth") : Carbon::now()->month;
        $endMonth = $request->filled("endMonth") ? $request->input("endMonth") : Carbon::now()->month;
        $startYear = $request->filled("startYear") ? $request->input("startYear") : Carbon::now()->year;
        $endYear = $request->filled("endYear") ? $request->input("endYear") : Carbon::now()->year;

        $start = Carbon::createFromDate($startYear, $startMonth, 1)
            ->startOfMonth()
            ->toDateTimeString(); // Output: 2024-01-01 00:00:00 (or current year)

        $end = Carbon::createFromDate($endYear, $endMonth, 1)
            ->endOfMonth()
            ->toDateTimeString(); // Output: 2024-01-01 00:00:00 (or current year)

        if ($request->filled("startMonth") || $request->filled("startYear")) {
            $query = $query->whereDate("paid_on", ">=", $start);
        }

        if ($request->filled("endMonth") || $request->filled("endYear")) {
            $query = $query->whereDate("paid_on", "<=", $end);
        }

        return $query;
    }

    /*
     * Generate Payment PDF
     */
    public function generatePdf($id)
    {
        $payment = Payment::findOrFail($id);

        // This looks for resources/views/payments/pdf.blade.php
        $pdf = Pdf::loadView('payments.pdf', compact('payment'));

        return $pdf;
    }

    /*
     * Send Payment by Email
     */
    public function sendEmail($id)
    {
        $payment = Payment::findOrFail($id);

        try {
            DB::beginTransaction();

            $generatedPdf = $this->generatePdf($id);

            $pdf = $generatedPdf->output();

            $payment->userUnit->user->notify(new PaymentNotification($payment, $pdf));

            // Save Email
            $emailService = new EmailService;

            $request = new Request([
                "userUnitId" => $payment->userUnit->id,
                "paymentId" => $payment->id,
                "email" => $payment->userUnit->user->email,
                "model" => $payment,
            ]);

            $emailService->store($request);

            DB::commit();
        } catch (HttpTransportException $exception) {
            DB::rollBack();

            Log::error("Payment Email Error: ".$exception->getMessage());

            throw $exception;
        }

        return ["Success", "Receipt Sent Successfully", $payment];
    }
}
