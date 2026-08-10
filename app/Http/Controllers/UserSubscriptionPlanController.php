<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserSubscriptionPlanResource;
use App\Http\Services\UserSubscriptionPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserSubscriptionPlanController extends Controller
{
    public function __construct(protected UserSubscriptionPlanService $service)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $userSubscriptionPlans = $this->service->index($request);

        return UserSubscriptionPlanResource::collection($userSubscriptionPlans)
            ->additional([
                'status' => true,
                'message' => $userSubscriptionPlans->count().' User Subscription plans retrieved successfully.',
            ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): void
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $this->validate($request, [
            'userId' => 'required|exists:users,id',
            'subscriptionPlanId' => 'required|exists:subscription_plans,id',
            'amountPaid' => 'nullable|numeric|min:0',
            'duration' => 'required|integer|min:1',
            'type' => 'required|string',
        ]);

        [$saved, $message, $userSubscriptionPlan] = $this->service->store($request);

        return response()->json([
            'success' => $saved,
            'message' => $message,
            'data' => $userSubscriptionPlan,
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(int|string $id): JsonResponse
    {
        $userSubscriptionPlan = $this->service->show($id);

        return response()->json([
            "status" => true,
            "message" => "User Subscription plan retrieved successfully.",
            "data" => new UserSubscriptionPlanResource($userSubscriptionPlan),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int|string $id): void
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int|string $id): JsonResponse
    {
        $this->validate($request, [
            'userId' => 'nullable|exists:users,id',
            'subscriptionPlanId' => 'nullable|exists:subscription_plans,id',
            'startDate' => 'nullable|date',
            'endDate' => 'nullable|date|after:startDate',
            'type' => 'nullable|string',
            'status' => 'nullable|string',
        ]);

        [$saved, $message, $userSubscriptionPlan] = $this->service->update($request, $id);

        return response()->json([
            "status" => $saved,
            "message" => $message,
            "data" => new UserSubscriptionPlanResource($userSubscriptionPlan),
        ], $saved ? 200 : 400);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int|string $id): JsonResponse
    {
        [$deleted, $message, $userSubscriptionPlan] = $this->service->destroy($id);

        return response()->json([
            "status" => $deleted,
            "message" => $message,
        ], $deleted ? 200 : 400);
    }
}
