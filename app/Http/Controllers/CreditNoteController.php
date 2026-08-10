<?php

namespace App\Http\Controllers;

use App\Http\Resources\CreditNoteResource;
use App\Http\Services\CreditNoteService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CreditNoteController extends Controller
{
    public function __construct(protected CreditNoteService $service)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return $this->service->index($request);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): Response
    {
        $this->validate($request, [
            "userUnitIds" => "required|array",
            "description" => "required|string",
            "amount" => "required|integer",
            "month" => "required|integer|min:1",
            "year" => "required|integer",
        ]);

        [$saved, $message, $creditNotes] = $this->service->store($request);

        return response([
            "status" => $saved,
            "message" => $message,
            "data" => $creditNotes,
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(int|string $id): CreditNoteResource
    {
        return $this->service->show($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int|string $id): Response
    {
        $this->validate($request, [
            "description" => "nullable|string",
            "amount" => "nullable|integer",
            "month" => "nullable|integer|min:1",
            "year" => "nullable|integer",
        ]);

        [$saved, $message, $creditNotes] = $this->service->update($request, $id);

        return response([
            "status" => $saved,
            "message" => $message,
            "data" => $creditNotes,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int|string $id): Response
    {
        [$deleted, $message, $creditNote] = $this->service->destroy($id);

        return response([
            "status" => $deleted,
            "message" => $message,
            "data" => $creditNote,
        ], 200);
    }

    /*
     * Get CreditNotes by Property ID
     */
    public function byPropertyId(Request $request, int|string $id): mixed
    {
        return $this->service->byPropertyId($request, $id);
    }
}
