<?php

namespace App\Http\Services;

use App\Models\SubscriptionPlan;
use App\Models\UserSubscriptionPlan;

class SubscriptionPlanService extends Service
{
    /**
     * Get all subscription plans.
     */
    public function index()
    {
        $subscriptionPlanQuery = new SubscriptionPlan;

        return $subscriptionPlanQuery
            ->orderBy("id", "ASC")
            ->paginate();
    }

    public function show($id)
    {
        $subscriptionPlan = SubscriptionPlan::findOrFail($id);

        return $subscriptionPlan;
    }

    public function store($request)
    {
        $subscriptionPlan = new SubscriptionPlan;
        $subscriptionPlan->name = $request->input("name");
        $subscriptionPlan->description = $request->input("description");
        $subscriptionPlan->price = $request->input("price");
        $subscriptionPlan->billing_cycle = $request->input("billingCycle");
        $subscriptionPlan->max_properties = $request->input("maxProperties");
        $subscriptionPlan->max_units = $request->input("maxUnits");
        $subscriptionPlan->max_users = $request->input("maxUsers");
        // $subscriptionPlan->features = $request->input("features");
        $saved = $subscriptionPlan->save();

        return [
            $saved,
            $saved ? "Subscription Plan Created Successfully." : "Failed to Create Subscription Plan.",
            $subscriptionPlan,
        ];
    }

    public function update($request, $id)
    {
        $subscriptionPlan = SubscriptionPlan::findOrFail($id);
        $subscriptionPlan->name = $request->input("name", $subscriptionPlan->name);
        $subscriptionPlan->description = $request->input("description", $subscriptionPlan->description);
        $subscriptionPlan->price = $request->input("price", $subscriptionPlan->price);
        $subscriptionPlan->billing_cycle = $request->input("billingCycle", $subscriptionPlan->billing_cycle);
        $subscriptionPlan->max_properties = $request->input("maxProperties", $subscriptionPlan->max_properties);
        $subscriptionPlan->max_units = $request->input("maxUnits", $subscriptionPlan->max_units);
        $subscriptionPlan->max_users = $request->input("maxUsers", $subscriptionPlan->max_users);
        // $subscriptionPlan->features = $request->input("features", $subscriptionPlan->features);
        $saved = $subscriptionPlan->save();

        return [
            $saved,
            $saved ? "Subscription Plan Updated Successfully." : "Failed to Update Subscription Plan.",
            $subscriptionPlan,
        ];
    }

    public function destroy($subscriptionPlan)
    {
        $hasActiveSubscriptions = UserSubscriptionPlan::where("subscription_plan_id", $subscriptionPlan->id)
            ->where(function ($query) {
                $query->whereNull("end_date")
                    ->orWhere("end_date", ">", now());
            })
            ->exists();

        if ($hasActiveSubscriptions) {
            return [false, "Cannot delete subscription plan with active subscriptions."];
        }

        $deleted = $subscriptionPlan->delete();

        return [
            $deleted,
            $deleted ? "Subscription Plan Deleted Successfully." : "Failed to Delete Subscription Plan.",
        ];
    }
}
