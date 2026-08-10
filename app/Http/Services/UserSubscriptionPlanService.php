<?php

namespace App\Http\Services;

use App\Models\UserSubscriptionPlan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class UserSubscriptionPlanService extends Service
{
    /**
     * Get all user subscription plans.
     */
    public function index(Request $request): LengthAwarePaginator
    {
        $query = UserSubscriptionPlan::query();

        $query = $this->search($query, $request);

        $userSubscriptionPlans = $query->orderBy("id", "DESC")
            ->paginate();

        return $userSubscriptionPlans;
    }

    public function show(int|string $id): UserSubscriptionPlan
    {
        $userSubscriptionPlan = UserSubscriptionPlan::findOrFail($id);

        return $userSubscriptionPlan;
    }

    /**
     * @return array{0: bool, 1: string, 2: UserSubscriptionPlan}
     */
    public function store(Request $request): array
    {
        // Check if the user is already subscribed to a plan
        $existingPlan = UserSubscriptionPlan::query()
            ->where("user_id", $request->userId)
            ->where("status", "active")
            ->first();

        if ($existingPlan) {
            // Throw Validation Exception if the user is already subscribed
            throw ValidationException::withMessages([
                "message" => "You are already subscribed to a plan.",
            ]);
        }

        $pendingPlanQuery = UserSubscriptionPlan::query()
            ->where("user_id", $request->userId)
            ->where("status", "pending");

        $pendingPlanExists = $pendingPlanQuery->exists();

        $pendingPlanQuery->delete();

        $userSubscriptionPlan = new UserSubscriptionPlan;
        $userSubscriptionPlan->user_id = $request->userId;
        $userSubscriptionPlan->subscription_plan_id = $request->subscriptionPlanId;
        $userSubscriptionPlan->amount_paid = $request->amountPaid;
        $userSubscriptionPlan->start_date = now();
        $userSubscriptionPlan->end_date = now()->addMonths($request->duration);
        $userSubscriptionPlan->status = $request->input("status", "pending");
        $userSubscriptionPlan->type = $request->input("type");
        $saved = $userSubscriptionPlan->save();

        return [
            $saved,
            "Subscription Plan " . ($pendingPlanExists ? "Unselected" : "Selected") . " Successfully.",
            $userSubscriptionPlan
        ];
    }

    /**
     * @return array{0: bool, 1: string, 2: UserSubscriptionPlan}
     */
    public function update(Request $request, int|string $id): array
    {
        $userSubscriptionPlan = UserSubscriptionPlan::findOrFail($id);
        $userSubscriptionPlan->subscription_plan_id = $request->input("subscriptionPlanId", $userSubscriptionPlan->subscription_plan_id);
        $userSubscriptionPlan->amount_paid = $request->input("amountPaid", $userSubscriptionPlan->amount_paid);

        if ($request->filled("startDate")) {
            $userSubscriptionPlan->start_date = $request->input("startDate");
        }

        if ($request->filled("endDate")) {
            $userSubscriptionPlan->end_date = $request->input("endDate");
        }

        $userSubscriptionPlan->status = $request->input("status", $userSubscriptionPlan->status);
        $userSubscriptionPlan->type = $request->input("type", $userSubscriptionPlan->type);
        $saved = $userSubscriptionPlan->save();

        return [
            $saved,
            $saved ? "User Subscription Plan Updated Successfully." : "Failed to Update User Subscription Plan.",
            $userSubscriptionPlan,
        ];
    }

    /**
     * @return array{0: bool, 1: string, 2: UserSubscriptionPlan}
     */
    public function destroy(int|string $id): array
    {
        $userSubscriptionPlan = UserSubscriptionPlan::findOrFail($id);
        $deleted = $userSubscriptionPlan->delete();

        return [
            $deleted,
            $deleted ? "User Subscription Plan Deleted Successfully." : "Failed to Delete User Subscription Plan.",
            $userSubscriptionPlan,
        ];
    }

    /**
     * Subscribe or update user subscription plan.
     */
    public function search(Builder $query, Request $request): Builder
    {
        if ($request->userId) {
            $query = $query->where("user_id", $request->userId);
        }

        if ($request->status) {
            $query = $query->where("status", $request->status);
        }

        return $query;
    }
}
