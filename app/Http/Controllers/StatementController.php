<?php

namespace App\Http\Controllers;

use App\Http\Resources\LoanStatementResource;
use App\Http\Resources\SubscriptionStatementResource;
use App\Http\Resources\UnitStatementResource;
use App\Http\Services\StatementService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StatementController extends Controller
{
    public function __construct(protected StatementService $service)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function unit(Request $request): AnonymousResourceCollection
    {
        [
            $paginator,
            $totalInvoices,
            $totalPayments,
            $totalCreditNotes,
            $totalDeductions
        ] = $this->service->unit($request);

        return UnitStatementResource::collection($paginator)
            ->additional([
                "due" => number_format($totalInvoices),
                "paid" => number_format($totalPayments),
                "balance" => number_format($totalInvoices - $totalCreditNotes - $totalPayments + $totalDeductions),
            ]);
    }

    public function subscription(Request $request): AnonymousResourceCollection
    {
        [
            $paginator,
            $totalInvoices,
            $totalPayments,
        ] = $this->service->subscription($request);

        return SubscriptionStatementResource::collection($paginator)
            ->additional([
                "due" => number_format($totalInvoices),
                "paid" => number_format($totalPayments),
                "balance" => number_format($totalInvoices - $totalPayments),
            ]);
    }

    public function loan(Request $request, int|string $id): AnonymousResourceCollection
    {
        [
            $paginator,
            $principal,
            $totalPayments,
            $balance
        ] = $this->service->loan($request, $id);

        // return $paginator;
        return LoanStatementResource::collection($paginator)
            ->additional([
                "principal" => $principal,
                "paid" => $totalPayments,
                "balance" => $balance,
            ]);
    }
}
