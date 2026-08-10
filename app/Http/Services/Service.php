<?php

namespace App\Http\Services;

use App\Models\CreditNote;
use App\Models\Deduction;
use App\Models\Invoice;
use App\Models\Payment;

class Service
{
    public ?string $id;

    public function __construct()
    {
        // Current User ID
        $auth = auth('sanctum')->user();

        $this->id = $auth ? $auth->id : null;
    }

    public function updateInvoiceStatus(int $userUnitId): void
    {
        $invoiceQuery = Invoice::query()->where("user_unit_id", $userUnitId);

        $invoices = $invoiceQuery
            ->orderBy("month", "ASC")
            ->orderBy("year", "ASC")
            ->get();

        $paymentQuery = Payment::query()->where("user_unit_id", $userUnitId);

        $totalPayments = $paymentQuery->sum("amount");

        $creditNoteQuery = CreditNote::query()->where("user_unit_id", $userUnitId);

        $totalCreditNotes = $creditNoteQuery->sum("amount");

        $deductionQuery = Deduction::query()->where("user_unit_id", $userUnitId);

        $totalDeductions = $deductionQuery->sum("amount");

        $paid = $totalPayments + $totalCreditNotes - $totalDeductions;

        $invoices->each(function (Invoice $invoice) use (&$paid): void {
            if ($paid <= 0) {
                $invoice->paid = 0;
                $invoice->balance = $invoice->amount;
                $invoice->status = "not_paid";
            } elseif ($paid < $invoice->amount) {
                $invoice->paid = $paid;
                $invoice->balance = $invoice->amount - $paid;
                $invoice->status = "partially_paid";
            } elseif ($paid >= $invoice->amount) {
                $invoice->paid = $invoice->amount;
                $invoice->balance = 0;
                $invoice->status = "paid";
            }

            $invoice->save();

            $paid -= $invoice->paid;
        });
    }
}
