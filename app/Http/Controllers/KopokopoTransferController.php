<?php

namespace App\Http\Controllers;

use App\Http\Services\KopokopoTransferService;
use App\Models\KopokopoTransfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class KopokopoTransferController extends Controller
{
    public function __construct(protected KopokopoTransferService $service)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): AnonymousResourceCollection
    {
        return $this->service->index();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): Response
    {
        [$saved, $message, $kopokopoTransfer] = $this->service->store($request);

        return response([
            "status" => $saved,
            "message" => $message,
            "data" => $kopokopoTransfer,
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(int|string $id): void
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, KopokopoTransfer $kopokopoTransfer): void
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(KopokopoTransfer $kopokopoTransfer): void
    {
        //
    }

    /*
     * Initiate Transfer
     */
    public function initiateTransfer(Request $request): Response
    {
        $this->validate($request, [
            "type" => "string|required",
            "destinationReference" => "string|required",
            "amount" => "string|required",
        ]);

        [$status, $message, $data] = $this->service->initiateTransfer($request);

        return response([
            "status" => $status,
            "message" => $message,
            "data" => $data,
        ], 200);
    }
}
