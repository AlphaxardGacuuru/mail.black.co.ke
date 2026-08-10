<?php

namespace App\Http\Controllers;

use App\Http\Services\SMSService;
use App\Models\SMS;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SMSController extends Controller
{
    public function __construct(protected SMSService $service)
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
    public function show(SMS $sMS): void
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SMS $sMS): void
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SMS $sMS): void
    {
        //
    }
}
