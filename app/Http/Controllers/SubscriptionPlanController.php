<?php

namespace App\Http\Controllers;

use App\Http\Resources\SubscriptionPlanResource;
use App\Http\Services\SubscriptionPlanService;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SubscriptionPlanController extends Controller
{
    public function __construct(protected SubscriptionPlanService $service) {}

    /**
     * Display a listing of the resource.
     */
    public function index(): AnonymousResourceCollection
    {
        $subscriptionPlans = $this->service->index();

        return SubscriptionPlanResource::collection($subscriptionPlans)
            ->additional([
                "status" => true,
                "message" => $subscriptionPlans->count()." Subscription plans retrieved successfully.",
            ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $this->validate($request, [
            "name" => "required|string|unique:subscription_plans,name",
            "description" => "required|string|max:10000",
            "price" => "required|array",
            "price.monthly" => "required|numeric|min:0",
            "price.yearly" => "required|numeric|min:0",
            "billingCycle" => "required|string",
            "maxProperties" => "required|numeric",
            "maxUnits" => "required|numeric",
            "maxUsers" => "required|numeric",
            "features" => "nullable|string",
        ]);

        [$saved, $message, $subscriptionPlan] = $this->service->store($request);

        return response()->json([
            "status" => $saved,
            "message" => $message,
            "data" => new SubscriptionPlanResource($subscriptionPlan),
        ], $saved ? 201 : 400);
    }

    /**
     * Display the specified resource.
     */
    public function show(int|string $id): JsonResponse
    {
        $subscriptionPlan = $this->service->show($id);

        return response()->json([
            "status" => true,
            "message" => "Subscription plan retrieved successfully.",
            "data" => new SubscriptionPlanResource($subscriptionPlan),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int|string $id): JsonResponse
    {
        $this->validate($request, [
            "name" => "nullable|string|unique:subscription_plans,name",
            "description" => "nullable|string|max:10000",
            "price" => "nullable|array",
            "price.monthly" => "nullable|numeric|min:0",
            "price.yearly" => "nullable|numeric|min:0",
            "billingCycle" => "nullable|string",
            "maxProperties" => "nullable|numeric",
            "maxUnits" => "nullable|numeric",
            "maxUsers" => "nullable|numeric",
            "features" => "nullable|string",
        ]);

        [$saved, $message, $subscriptionPlan] = $this->service->update($request, $id);

        return response()->json([
            "status" => $saved,
            "message" => $message,
            "data" => new SubscriptionPlanResource($subscriptionPlan),
        ], $saved ? 200 : 400);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SubscriptionPlan $subscriptionPlan): JsonResponse
    {
        [$deleted, $message] = $this->service->destroy($subscriptionPlan);

        return response()->json([
            "status" => $deleted,
            "message" => $message,
        ], $deleted ? 200 : 400);
    }
}
