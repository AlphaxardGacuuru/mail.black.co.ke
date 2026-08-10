<?php

namespace App\Http\Services;

use App\Models\CreditNote;
use App\Models\Deduction;
use App\Models\Invoice;
use App\Models\Loan;
use App\Models\Payment;
use Carbon\Carbon;
use Cknow\Money\Money;
use Illuminate\Pagination\LengthAwarePaginator;

class StatementService extends Service
{
    /*
     * Fetch Unit Statements
     */
    public function unit($request)
    {
        $invoiceQuery = new Invoice;
        $invoiceQuery = $this->search($invoiceQuery, $request);
        $invoiceQuery = $invoiceQuery->select("*", "amount as invoiceDebit");

        $totalInvoices = $invoiceQuery->sum("amount");

        $invoices = $invoiceQuery
            ->orderBy("month", "ASC")
            ->orderBy("year", "ASC")
            ->get();

        $paymentQuery = new Payment;
        $paymentQuery = $this->search($paymentQuery, $request);
        $paymentQuery = $paymentQuery->select("*", "amount as paymentCredit");

        $totalPayments = $paymentQuery->sum("amount");

        $payments = $paymentQuery
            ->orderBy("month", "ASC")
            ->orderBy("year", "ASC")
            ->get();

        $creditNoteQuery = new CreditNote;
        $creditNoteQuery = $this->search($creditNoteQuery, $request);
        $creditNoteQuery = $creditNoteQuery->select("*", "amount as creditNoteCredit");

        $totalCreditNotes = $creditNoteQuery->sum("amount");

        $creditNotes = $creditNoteQuery
            ->orderBy("month", "ASC")
            ->orderBy("year", "ASC")
            ->get();

        $deductionQuery = new Deduction;
        $deductionQuery = $deductionQuery->select("*", "amount as deductionDebit");
        $deductionQuery = $this->search($deductionQuery, $request);

        $totalDeductions = $deductionQuery->sum("amount");

        $deductions = $deductionQuery
            ->orderBy("month", "ASC")
            ->orderBy("year", "ASC")
            ->get();

        $balance = 0;

        $statements = $invoices
            ->concat($payments)
            ->concat($creditNotes)
            ->concat($deductions)
            ->groupBy(function ($item) {
                // Ensure month and year are always two/four digits
                $month = str_pad($item->month, 2, '0', STR_PAD_LEFT);
                $year = strlen($item->year) === 2 ? '20'.$item->year : $item->year;

                return "{$year}-{$month}";
            })
            ->sortKeys()
            ->flatten()
            ->map(function ($item) use (&$balance) {
                if ($item->invoiceDebit) {
                    $item->type = "Invoice";
                    $item->debit = $item->invoiceDebit;
                    $balance += $item->invoiceDebit;
                } elseif ($item->paymentCredit) {
                    $item->type = "Payment";
                    $item->credit = $item->paymentCredit;
                    $balance -= $item->paymentCredit;
                } elseif ($item->creditNoteCredit) {
                    $item->type = "Credit Note";
                    $item->credit = $item->creditNoteCredit;
                    $balance -= $item->creditNoteCredit;
                } else {
                    $item->type = "Deduction";
                    $item->debit = $item->deductionDebit;
                    $balance += $item->deductionDebit;
                }

                $item->balance = $balance;

                return $item;
            })
            ->reverse()
            ->values();

        // Get current page from the request, default is 1
        $currentPage = $request->input("page", 1);

        // Define how many items we want to be visible in each page
        $perPage = 20;

        // Slice the collection to get the items to display in current page
        $currentItems = $statements
            ->slice(($currentPage - 1) * $perPage, $perPage)
            ->values();

        // Create paginator
        $paginator = new LengthAwarePaginator(
            $currentItems,
            $statements->count(),
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return [
            $paginator,
            $totalInvoices,
            $totalPayments,
            $totalCreditNotes,
            $totalDeductions,
        ];
    }

    /*
     * Fetch Subscription Statements
     */
    public function subscription($request)
    {
        $userSubscriptionQuery = new UserSubscriptionPlan;
        $userSubscriptionQuery = $this->search($userSubscriptionQuery, $request);
        $userSubscriptionQuery = $userSubscriptionQuery->select("*", "amount_paid as debit");

        $totalInvoices = $userSubscriptionQuery->sum("amount_paid");

        $invoices = $userSubscriptionQuery
            ->orderBy("id", "DESC")
            ->get();

        $mpesaTransactionQuery = new MPESATransaction;
        $mpesaTransactionQuery = $this->search($mpesaTransactionQuery, $request);
        $mpesaTransactionQuery = $mpesaTransactionQuery->select("*", "amount as mpesaCredit");

        $totalPayments = $mpesaTransactionQuery->sum("amount");

        $mpesaPayments = $mpesaTransactionQuery
            ->orderBy("id", "DESC")
            ->get();

        $balance = 0;

        $statements = $invoices
            ->concat($mpesaPayments)
            ->groupBy(fn ($item) => $item->created_at)
            ->sortKeys()
            ->flatten()
            ->map(function ($item) use (&$balance) {
                if ($item->debit) {
                    $item->type = "Subscription";
                    $item->debit = $item->debit;
                    $balance += $item->debit;
                } elseif ($item->mpesaCredit) {
                    $item->type = "Mpesa Payment";
                    $item->credit = $item->mpesaCredit;
                    $balance -= $item->mpesaCredit;
                }

                $item->balance = $balance;

                return $item;
            })
            ->reverse()
            ->values();

        // Get current page from the request, default is 1
        $currentPage = $request->input("page", 1);

        // Define how many items we want to be visible in each page
        $perPage = 20;

        // Slice the collection to get the items to display in current page
        $currentItems = $statements
            ->slice(($currentPage - 1) * $perPage, $perPage)
            ->values();

        // Create paginator
        $paginator = new LengthAwarePaginator(
            $currentItems,
            $statements->count(),
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return [
            $paginator,
            $totalInvoices,
            $totalPayments,
        ];
    }

    public function loan($request, $id)
    {
        $loan = Loan::with("repayments")
            ->findOrFail($id);

        $principal = Money::KES($loan->amount);
        $totalPayments = Money::KES($loan->repayments->sum("amount"));

        $disbursementDate = Carbon::parse($loan->getRawOriginal("disbursed_at"));
        $dueDateRaw = $loan->getRawOriginal("due_date");
        $dueDate = Carbon::parse($dueDateRaw);
        $durationMonths = max(1, $disbursementDate->diffInMonths($dueDate));

        $storedInterest = $loan->interest;
        $annualInterestRate = $storedInterest / 100;
        $monthlyInterestRate = $annualInterestRate / 12;

        if ($monthlyInterestRate > 0) {
            $monthlyInstallment = $principal
                ->multiply($monthlyInterestRate)
                ->multiply(pow(1 + $monthlyInterestRate, $durationMonths))
                ->divide(pow(1 + $monthlyInterestRate, $durationMonths) - 1);
        } else {
            $monthlyInstallment = $principal->divide($durationMonths);
        }

        $repayments = $loan->repayments
            ->sortBy(function ($repayment) {
                return strtotime($repayment->getRawOriginal("created_at"));
            })
            ->values();

        $repaymentIndex = 0;
        $currentRepaymentRemaining = Money::KES($repayments->get(0)?->amount ?? 0);
        $outstandingPrincipal = $principal;

        $schedule = collect([]);

        for ($period = 1; $period <= $durationMonths; $period++) {
            $interest = $outstandingPrincipal->multiply($monthlyInterestRate);
            $principalComponent = $monthlyInstallment->subtract($interest);

            if ($period === $durationMonths) {
                $principalComponent = $outstandingPrincipal;
                $monthlyInstallment = $principalComponent->add($interest);
            }

            $expectedAmount = $monthlyInstallment;
            $expectedDate = $disbursementDate
                ->copy()
                ->addMonths($period)
                ->startOfDay();

            $paidAmount = Money::KES(0);
            $paymentDates = collect([]);
            $remainingExpected = $expectedAmount;

            while ($remainingExpected->getAmount() > 0.0001 && $repaymentIndex < $repayments->count()) {
                if ($currentRepaymentRemaining->getAmount() <= 0.0001) {
                    $repaymentIndex++;
                    $currentRepaymentRemaining = Money::KES($repayments->get($repaymentIndex)?->amount ?? 0);

                    continue;
                }

                $allocation = Money::min($remainingExpected, $currentRepaymentRemaining);
                $paidAmount = $paidAmount->add($allocation);
                $remainingExpected = $remainingExpected->subtract($allocation);
                $currentRepaymentRemaining = $currentRepaymentRemaining->subtract($allocation);

                $repayment = $repayments->get($repaymentIndex);

                if ($repayment) {
                    $paymentDates->push(Carbon::parse($repayment->getRawOriginal("created_at")));
                }
            }

            $remainingAmount = Money::max(Money::KES(0), $expectedAmount->subtract($paidAmount));

            $status = "not paid";
            if ($remainingAmount->getAmount() <= 0.009) {
                $status = "paid";
            } elseif ($paidAmount->getAmount() > 0) {
                $status = "partial";
            }

            $outstandingPrincipal = Money::max(Money::KES(0), $outstandingPrincipal->subtract($principalComponent));

            $schedule->push([
                "period" => $period,
                "month" => (int) $expectedDate->format("m"),
                "year" => (int) $expectedDate->format("Y"),
                "expectedDate" => $expectedDate->format("d M Y"),
                "expectedAmount" => $expectedAmount->format(),
                "principal" => $principalComponent->format(),
                "interest" => $interest->format(),
                "paidAmount" => $paidAmount->format(),
                "remainingAmount" => $remainingAmount->format(),
                "status" => $status,
                "paidAt" => $paymentDates->isNotEmpty() ? $paymentDates->last()->format("d M Y") : null,
                "balance" => $outstandingPrincipal->format(),
            ]);
        }

        $currentPage = (int) $request->input("page", 1);
        $perPage = 20;

        $currentItems = $schedule
            ->slice(($currentPage - 1) * $perPage, $perPage)
            ->values();

        $paginator = new LengthAwarePaginator(
            $currentItems,
            $schedule->count(),
            $perPage,
            $currentPage,
            [
                "path" => $request->url(),
                "query" => $request->query(),
            ]
        );

        return [
            $paginator,
            $principal->format(),
            $totalPayments->format(),
            Money::KES($loan->balance)->format(),
        ];
    }

    /*
     * Search
     */
    public function search($query, $request)
    {
        if ($request->filled("userUnitId") && $request->userUnitId != "undefined") {
            $query = $query->where("user_unit_id", $request->userUnitId);
        }

        if ($request->filled("unitId")) {
            $unitId = $request->input("unitId");

            $query = $query->whereHas("userUnit", function ($query) use ($unitId) {
                $query->where("unit_id", $unitId);
            });
        }

        if ($request->filled("tenantId")) {
            $tenantId = $request->input("tenantId");

            $query = $query->whereHas("userUnit", function ($query) use ($tenantId) {
                $query->where("user_id", $tenantId);
            });
        }

        if ($request->filled("subscriptionUserId")) {
            $query = $query->where("user_id", $request->subscriptionUserId);
        }

        return $query;
    }
}
