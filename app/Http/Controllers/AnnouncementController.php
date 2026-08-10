<?php

namespace App\Http\Controllers;

use App\Events\AnnouncementCreatedEvent;
use App\Http\Resources\AnnouncementResource;
use App\Http\Services\AnnouncementService;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AnnouncementController extends Controller
{
    public function __construct(protected AnnouncementService $service)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $announcements = $this->service->index($request);

        return AnnouncementResource::collection($announcements);
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
    public function store(Request $request): JsonResponse
    {
        $this->validate($request, [
            "message" => "required|string",
            "channels" => "required|array",
            "channels.*" => "in:email,sms",
            "userUnitIds" => "required|array",
        ]);

        [$saved, $message, $announcement] = $this->service->store($request);

        AnnouncementCreatedEvent::dispatchIf($saved, $announcement);

        return response()->json([
            "saved" => $saved,
            "message" => $message,
            "announcement" => $announcement,
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(Announcement $announcement): void
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Announcement $announcement): void
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Announcement $announcement): void
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Announcement $announcement): void
    {
        //
    }
}
