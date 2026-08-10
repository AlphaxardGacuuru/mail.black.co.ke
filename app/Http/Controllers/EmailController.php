<?php

namespace App\Http\Controllers;

use App\Http\Services\EmailService;
use App\Models\Email;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EmailController extends Controller
{
    public function __construct(protected EmailService $service)
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
    public function store(Request $request): void
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Email $email): void
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Email $email): void
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Email $email): void
    {
        //
    }
}
