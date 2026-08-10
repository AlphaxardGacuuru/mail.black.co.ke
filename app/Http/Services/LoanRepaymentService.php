<?php

namespace App\Http\Services;

use App\Models\Loan;
use App\Models\LoanRepayment;
use Cknow\Money\Money;
use Illuminate\Support\Facades\DB;

class LoanRepaymentService extends Service
{
    public function index($request)
    {
        $query = LoanRepayment::query()->with('loan.user');

        $query = $this->search($query, $request);

        $repayments = $query
            ->orderBy('created_at', 'ASC')
            ->paginate(20);

        return [true, "Loan Payments Retrieved Successfully.", $repayments];
    }

    public function show($id)
    {
        $repayment = LoanRepayment::with('loan.user')->findOrFail($id);

        return [true, "Loan Payment Retrieved Successfully.", $repayment];
    }

    public function store($request)
    {
        $loan = Loan::findOrFail($request->loanId);

        return $this->repayLoan(
            $loan,
            $request->amount,
            'manual',
            null,
            null,
            $request->transactionReference
        );
    }

    public function update($request, $id)
    {
        $repayment = LoanRepayment::with('loan')->findOrFail($id);
        $loan = $repayment->loan;

        $oldAmount = (int) $repayment->amount;
        $newAmount = $oldAmount;

        if ($request->filled('amount')) {
            $newAmount = (int) Money::parseByDecimal((string) $request->amount, 'KES')->getAmount();
            $repayment->amount = $newAmount;
        }

        if ($request->has('transactionReference')) {
            $repayment->transaction_reference = $request->transactionReference;
        }

        $updated = DB::transaction(function () use ($repayment, $loan, $oldAmount, $newAmount) {
            $saved = $repayment->save();

            $loan->balance = max(0, ((int) $loan->balance + $oldAmount) - $newAmount);

            if ((int) $loan->balance <= 0) {
                $loan->status = 'paid';
            } elseif ($loan->status === 'paid') {
                $loan->status = 'active';
            }

            $loan->save();

            return $saved;
        });

        $repayment->refresh();

        return [$updated, "Loan Payment Updated Successfully.", $repayment];
    }

    public function destroy($id)
    {
        $repayment = LoanRepayment::with('loan')->findOrFail($id);
        $loan = $repayment->loan;

        $deleted = DB::transaction(function () use ($repayment, $loan) {
            $repaymentAmount = (int) $repayment->amount;

            $deleted = $repayment->delete();

            $loan->balance = (int) $loan->balance + $repaymentAmount;

            if ((int) $loan->balance > 0 && $loan->status === 'paid') {
                $loan->status = 'active';
            }

            $loan->save();

            return $deleted;
        });

        return [$deleted, "Loan Payment Deleted Successfully.", $repayment];
    }

    public function search($query, $request)
    {
        if ($request->filled('loanId')) {
            $query->where('loan_id', $request->loanId);
        }

        if ($request->filled('transactionReference')) {
            $query->where('transaction_reference', 'like', '%'.$request->transactionReference.'%');
        }

        return $query;
    }

    /**
     * Record a repayment.
     */
    public function repayLoan($loan, $amount, $source, $sourceId = null, $sourceType = null, $transactionReference = null)
    {
        if ($loan->status === 'paid') {
            return [false, "Loan is already fully paid.", null];
        }

        $repaymentAmount = Money::parseByDecimal($amount, 'KES');
        $currentBalance = Money::KES($loan->balance);
        $remainingBalance = Money::max(
            Money::KES(0),
            $currentBalance->subtract($repaymentAmount)
        );

        $repayment = null;

        DB::transaction(function () use ($loan, $repaymentAmount, $remainingBalance, $source, $sourceId, $sourceType, $transactionReference, &$repayment) {
            $repayment = $loan->repayments()->create([
                'amount' => $repaymentAmount->getAmount(),
                'source' => $source,
                'source_id' => $sourceId,
                'source_type' => $sourceType,
                'transaction_reference' => $transactionReference,
            ]);

            $loan->balance = $remainingBalance->getAmount();

            if ($remainingBalance->getAmount() <= 0) {
                $loan->status = 'paid';
            }

            $loan->save();
        });

        return [true, "Loan Payment Recorded Successfully.", $repayment];
    }
}
