<?php

namespace App\Http\Services;

use App\Models\Loan;
use App\Models\User;
use Carbon\Carbon;
use Cknow\Money\Money;

class LoanService extends Service
{
    /*
    * Get All Loans
    */
    public function index($request)
    {
        $query = Loan::query();

        $query = $this->search($query, $request);

        $loans = $query
            ->orderBy('created_at', 'DESC')
            ->paginate();

        $totalAmount = $query->sum("amount");
        $totalApproved = $query->where("status", "approved")->sum("amount");
        $totalArrears = $query->where("status", "approved")->sum("amount");

        $totalAmount = Money::KES($totalAmount)->format();
        $totalApproved = Money::KES($totalApproved)->format();
        $totalArrears = Money::KES($totalArrears)->format();

        return [true, "Loans Retrieved Successfully.", $loans, $totalAmount, $totalApproved, $totalArrears];
    }

    public function show($id, $request)
    {
        $loan = Loan::findOrFail($id);

        return [true, "Loan Retrieved Successfully.", $loan];
    }

    /**
     * Create a new loan request.
     */
    public function store($request): array
    {
        $requestedAmountMinor = (int) Money::parseByDecimal((string) $request->amount, 'KES')->getAmount();
        $requestedAmountDecimal = (float) $request->amount;

        $limit = $this->getLimit($request->userId);

        if ($requestedAmountDecimal > $limit['availableLimit']) {
            return [false, "Requested amount exceeds available limit of ".number_format($limit['availableLimit']), null];
        }

        // Create Snapshot
        $snapshot = $limit;

        unset($snapshot['availableLimit']); // Keep raw data

        $loan = new Loan;
        $loan->user_id = $request->userId;
        $loan->interest = $request->interest;
        $loan->duration = $request->duration;
        $loan->amount = $requestedAmountMinor;
        $loan->balance = Money::KES($requestedAmountMinor)
            ->multiply(1 + $request->interest / 100)
            ->getAmount(); // Start with full balance + interest
        $loan->limit_snapshot = $snapshot;
        $loan->status = 'pending'; // Requires approval workflow usually
        $loan->due_date = Carbon::now()->addMonths($request->duration);
        $loan->disbursed_at = null; // Set when approved/paid
        $saved = $loan->save();

        return [$saved, "Loan Request Created Successfully.", $loan];
    }

    public function update($request, $id)
    {
        $loan = Loan::findOrFail($id);
        $loan->interest = $request->input("interest", $loan->interest);
        $loan->duration = $request->input("duration", $loan->duration);

        if ($request->filled("amount")) {
            $loan->amount = (int) Money::parseByDecimal((string) $request->amount, 'KES')->getAmount();
        }

        $loan->balance = Money::KES((int) $loan->amount)
            ->multiply(1 + $loan->interest / 100)
            ->getAmount();
        $loan->status = $request->input("status", $loan->status);

        if ($request->filled("dueDate")) {
            $loan->due_date = Carbon::parse($request->dueDate);
        } elseif ($request->filled("duration")) {
            $loan->due_date = Carbon::now()->addMonths((int) $loan->duration);
        }

        $updated = $loan->save();

        return [$updated, "Loan Updated Successfully.", $loan];
    }

    public function destroy($id)
    {
        $loan = Loan::findOrFail($id);

        $deleted = $loan->delete();

        return [$deleted, "Loan Deleted Successfully.", $loan];
    }

    public function search($query, $request)
    {
        if ($request->filled("number")) {
            $number = $request->number;

            $query->where("number", "LIKE", "%".$number."%");
        }

        if ($request->filled("userId")) {
            $query->where("user_id", $request->userId);
        }

        if ($request->filled("status")) {
            $query->where("status", $request->status);
        }

        return $query;
    }

    /**
     * Calculate loan limit for a user based on their property portfolio.
     */
    public function getLimit($userId)
    {
        $user = User::findOrFail($userId);

        // 1. Get properties owned directly
        $directProperties = $user
            ->properties()
            ->with(['units' => function ($query) {
                $query->where('status', 'occupied');
            }])
            ->get();

        // 2. Get properties where user is staff with 'owner' role
        $userProperties = $user
            ->userProperties()
            ->where('type', 'owner')
            ->get();

        $staffProperties = collect();

        foreach ($userProperties as $userProperty) {
            $property = $userProperty
                ->property()
                ->with(['units' => function ($query) {
                    $query->where('status', 'occupied');
                }])
                ->first();

            $staffProperties->push($property);
        }

        // 3. Merge and unique properties
        $allProperties = $directProperties
            ->merge($staffProperties)
            ->unique('id')
            ->filter();

        // 4. Calculate stats
        $totalUnits = 0;
        $occupiedUnits = 0; // If status tracking is available
        $totalPotentialRent = 0;

        foreach ($allProperties as $property) {
            $units = $property->units;
            $totalUnits += $units->count();

            // Assuming we take full potential rent for now, or filter by occupied if status exists
            // Since 'status' wasn't explicitly seen in Unit migration (default might be null),
            // I'll sum 'rent' for all units. Risk adjustment handles vacancy.
            $totalPotentialRent += $units->sum('rent');
        }

        // 5. Existing Debt
        $activeLoansDetails = $user->loans()
            ->whereIn('status', ['active', 'pending', 'approved'])
            ->get();

        $currentDebt = $activeLoansDetails->sum('balance');

        // 6. Formula: Limit = (Total Rent * 50%) - Current Debt
        // Adjust the multiplier as needed (e.g., 50% of monthly rent roll)
        $grossLimit = $totalPotentialRent * 0.50;
        $availableLimit = max(0, $grossLimit - $currentDebt);

        return [
            'grossLimit' => $grossLimit,
            'currentDebt' => $currentDebt,
            'availableLimit' => $availableLimit,
            'propertyCount' => $allProperties->count(),
            'unitCount' => $totalUnits,
            'totalRentRoll' => $totalPotentialRent,
            'durationOptions' => [
                ['value' => 3, 'label' => '3 Months'],
                ['value' => 6, 'label' => '6 Months'],
                ['value' => 12, 'label' => '12 Months'],
            ],
        ];
    }
}
