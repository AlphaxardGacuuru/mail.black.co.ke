<?php

namespace App\Http\Controllers;

use App\Http\Services\KopokopoRecipientService;
use App\Models\KopokopoRecipient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class KopokopoRecipientController extends Controller
{
    public function __construct(protected KopokopoRecipientService $service)
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
        $this->validate($request, [
            "type" => "string|required",
            "firstName" => "string|nullable",
            "lastName" => "string|nullable",
            "email" => "string|nullable",
            "phoneNumber" => "string|nullable",
            "accountName" => "string|nullable",
            "accountNumber" => "string|nullable",
            "bankBranchRef" => "string|nullable",
            "tillName" => "string|nullable",
            "tillNumber" => "string|nullable",
            "paybillName" => "string|nullable",
            "paybillNumber" => "string|nullable",
            "paybillAccountNumber" => "string|nullable",
            "description" => "string|required",
        ]);

        [$saved, $message, $kopokopoRecipient] = $this->service->store($request);

        return response([
            "status" => $saved,
            "message" => $message,
            "data" => $kopokopoRecipient,
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(int|string $id): AnonymousResourceCollection
    {
        return $this->service->show($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, KopokopoRecipient $kopokopoRecipient): void
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(KopokopoRecipient $kopokopoRecipient): void
    {
        //
    }
}
