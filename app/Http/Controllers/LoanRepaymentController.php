<?php

namespace App\Http\Controllers;

use App\Http\Resources\LoanRepaymentResource;
use App\Http\Services\LoanRepaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LoanRepaymentController extends Controller
{
    public function __construct(protected LoanRepaymentService $service)
    {
        //
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        [$status, $message, $repayments] = $this->service->index($request);

        return LoanRepaymentResource::collection($repayments)->additional([
            "status" => $status,
            "message" => $message,
        ]);
    }

    public function store(Request $request): JsonResponse|LoanRepaymentResource
    {
        $request->validate([
            "loanId" => "required|exists:loans,id",
            "amount" => "required|numeric|min:1",
            "transactionReference" => "nullable|string|max:255",
        ]);

        [$saved, $message, $repayment] = $this->service->store($request);

        if (! $saved || ! $repayment) {
            return response()->json([
                "status" => $saved,
                "message" => $message,
                "data" => null,
            ], 200);
        }

        return (new LoanRepaymentResource($repayment))->additional([
            "status" => $saved,
            "message" => $message,
        ]);
    }

    public function show(int|string $id): LoanRepaymentResource
    {
        [$status, $message, $repayment] = $this->service->show($id);

        return (new LoanRepaymentResource($repayment))->additional([
            "status" => $status,
            "message" => $message,
        ]);
    }

    public function update(Request $request, int|string $id): LoanRepaymentResource
    {
        $request->validate([
            "amount" => "sometimes|numeric|min:1",
            "transactionReference" => "nullable|string|max:255",
        ]);

        [$updated, $message, $repayment] = $this->service->update($request, $id);

        return (new LoanRepaymentResource($repayment))->additional([
            "status" => $updated,
            "message" => $message,
        ]);
    }

    public function destroy(int|string $id): LoanRepaymentResource
    {
        [$deleted, $message, $repayment] = $this->service->destroy($id);

        return (new LoanRepaymentResource($repayment))->additional([
            "status" => $deleted,
            "message" => $message,
        ]);
    }
}
