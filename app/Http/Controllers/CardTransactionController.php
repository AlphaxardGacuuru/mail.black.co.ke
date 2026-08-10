<?php

namespace App\Http\Controllers;

use App\Http\Services\CardTransactionService;
use App\Models\CardTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CardTransactionController extends Controller
{
    public function __construct(protected CardTransactionService $service)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): array|AnonymousResourceCollection
    {
        return $this->service->index($request);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): Response
    {
        [$saved, $message, $cardTransaction] = $this->service->store($request);

        return response([
            "status" => $saved,
            "message" => $message,
            "data" => $cardTransaction,
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(CardTransaction $cardTransaction): void
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, CardTransaction $cardTransaction): void
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CardTransaction $cardTransaction): void
    {
        //
    }
}
