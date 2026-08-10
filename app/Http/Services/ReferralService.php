<?php

namespace App\Http\Services;

use App\Models\Referral;
use App\Models\User;

class ReferralService extends Service
{
    public function index($request)
    {
        $query = Referral::with(['referer', 'referee']);

        $query = $this->search($query, $request);

        $referrals = $query
            ->orderBy("id", "DESC")
            ->paginate(20)
            ->appends($request->all());

        // Calculate totals efficiently
        $totalPayouts = 0;
        $totalIncome = 0;
        $totalBalance = 0;

        // Transform the collection to add calculated fields
        $referrals
            ->getCollection()
            ->transform(function ($referral) use (&$totalIncome, &$totalPayouts, &$totalBalance) {
                // Calculate income from this referee's subscription plans
                $subscriptionsPaid = $referral->referee->userSubscriptionPlans->sum('amount_paid');
                $commission = $referral->commission / 100;
                $refereeIncome = $subscriptionsPaid * $commission;

                // Get payouts made for this referral
                $refereePayout = $referral->referee->referralPayouts->sum('amount');

                // Calculate balance for this referral
                $balance = $refereeIncome - $refereePayout;

                // Add calculated fields to the referral
                $referral->calculated_income = $refereeIncome;
                $referral->calculated_payout = $refereePayout;
                $referral->calculated_balance = $balance;

                // Add to totals
                $totalIncome += $refereeIncome;
                $totalPayouts += $refereePayout;
                $totalBalance += $balance;

                return $referral;
            });

        return [$referrals, $totalPayouts, $totalIncome, $totalBalance];
    }

    public function store($request)
    {
        $refererId = User::where("email", $request->referer)->first()->id;

        $referral = new Referral;
        $referral->referer_id = $refererId;
        $referral->referee_id = $this->id;
        $saved = $referral->save();

        return [$saved, "Referral Saved Successfully", $referral];
    }

    /*
     * Handle Search
     */
    public function search($query, $request)
    {
        $isSuper = auth("sanctum")->user()->hasRole("Super Admin");

        if ($request->filled("name")) {
            $query = $query->where("name", "LIKE", "%".$request->name."%");
        }

        if ($request->filled("email")) {
            $query = $query->where("email", "LIKE", "%".$request->email."%");
        }

        if ($request->filled("refererId")) {
            if (! $isSuper) {
                $query = $query->where("referer_id", $request->refererId);
            }
        }

        if ($request->filled("refereeId")) {
            $query = $query->where("referee_id", $request->refereeId);
        }

        $startMonth = $request->input("startMonth");
        $endMonth = $request->input("endMonth");
        $startYear = $request->input("startYear");
        $endYear = $request->input("endYear");

        if ($request->filled("startMonth")) {
            $query = $query->where("month", ">=", $startMonth);
        }

        if ($request->filled("endMonth")) {
            $query = $query->where("month", "<=", $endMonth);
        }

        if ($request->filled("startYear")) {
            $query = $query->where("year", ">=", $startYear);
        }

        if ($request->filled("endYear")) {
            $query = $query->where("year", "<=", $endYear);
        }

        return $query;
    }
}
