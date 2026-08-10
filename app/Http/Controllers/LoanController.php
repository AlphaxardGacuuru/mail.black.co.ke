<?php

namespace App\Http\Controllers;

use App\Http\Resources\LoanResource;
use App\Http\Services\LoanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LoanController extends Controller
{
    public function __construct(protected LoanService $service)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        [$status, $message, $loans, $totalAmount, $totalApproved, $totalArrears] = $this->service->index($request);

        return LoanResource::collection($loans)
            ->additional([
                'status' => $status,
                'message' => $message,
                'totalAmount' => $totalAmount,
                'totalApproved' => $totalApproved,
                'totalArrears' => $totalArrears,
            ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): LoanResource
    {
        $request->validate([
            'userId' => 'required|exists:users,id',
            'interest' => 'required|numeric|min:0',
            'amount' => 'required|numeric|min:100',
            'duration' => 'required|in:3,6,12',
        ]);

        [$status, $message, $loan] = $this->service->store($request);

        return (new LoanResource($loan))->additional([
            "status" => $status,
            "message" => $message,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, int|string $id): LoanResource
    {
        [$status, $message, $loan] = $this->service->show($id, $request);

        return (new LoanResource($loan))->additional([
            "status" => $status,
            "message" => $message,
        ]);
    }

    public function destroy(int|string $id): LoanResource
    {
        [$deleted, $message, $loan] = $this->service->destroy($id);

        return (new LoanResource($loan))->additional([
            "status" => $deleted,
            "message" => $message,
        ]);
    }

    public function update(Request $request, int|string $id): LoanResource
    {
        $request->validate([
            "interest" => "sometimes|numeric|min:0",
            "amount" => "sometimes|numeric|min:100",
            "duration" => "sometimes|in:3,6,12",
            "status" => "sometimes|string",
            "dueDate" => "sometimes|date",
        ]);

        [$updated, $message, $loan] = $this->service->update($request, $id);

        return (new LoanResource($loan))->additional([
            "status" => $updated,
            "message" => $message,
        ]);
    }

    /*
    * Get Loan Limit
    */
    public function getLimit(int|string $userId): JsonResponse
    {
        $limit = $this->service->getLimit($userId);

        return response()->json([
            "status" => "success",
            "message" => "Credit Limit Retrieved Successfully",
            "data" => $limit,
        ]);
    }
}
