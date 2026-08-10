<?php

namespace App\Http\Controllers;

use App\Events\ReferralCreatedEvent;
use App\Http\Resources\ReferralResource;
use App\Http\Services\ReferralService;
use App\Models\Referral;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ReferralController extends Controller
{
    public function __construct(protected ReferralService $service) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        [$referrals, $payouts, $paid, $balance] = $this->service->index($request);

        return ReferralResource::collection($referrals)
            ->additional([
                "status" => true,
                "message" => "Referrals fetched successfully",
                "balance" => number_format($balance),
                "paid" => number_format($paid),
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
    public function store(Request $request): Response
    {
        $this->validate($request, [
            "referer" => "required|email",
        ]);

        [$saved, $message, $referral] = $this->service->store($request);

        ReferralCreatedEvent::dispatchIf($saved, $referral);

        return response([
            "status" => $saved,
            "message" => $message,
            "data" => $referral,
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(Referral $referral): void
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Referral $referral): void
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateReferralRequest $request, Referral $referral): void
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Referral $referral): void
    {
        //
    }
}
