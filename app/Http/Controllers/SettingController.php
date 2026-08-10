<?php

namespace App\Http\Controllers;

use App\Http\Services\SettingService;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function __construct(protected SettingService $service)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        [$saved, $message, $settings] = $this->service->index($request);

        return response()->json([
            'saved' => $saved,
            'message' => $message,
            'data' => $settings,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $this->validate($request, [
            'key' => 'required|string',
            'value' => 'required|string',
        ]);

        [$saved, $setting, $message] = $this->service->store($request);

        return response()->json([
            'saved' => $saved,
            'message' => $message,
            'setting' => $setting,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Setting $setting): void
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Setting $setting): void
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Setting $setting): void
    {
        //
    }
}
