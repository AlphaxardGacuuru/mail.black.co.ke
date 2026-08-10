<?php

namespace App\Http\Controllers;

use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Http\Resources\SupportTicketResource;
use App\Http\Services\SupportTicketService;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SupportTicketController extends Controller
{
    public function __construct(protected SupportTicketService $service) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        [$status, $message, $supportTickets, $categories, $priorities, $statuses] = $this->service->index($request);

        return SupportTicketResource::collection($supportTickets)
            ->additional([
                "status" => $status,
                "message" => $message,
                "categories" => $categories,
                "priorities" => $priorities,
                "statuses" => $statuses,
            ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): SupportTicketResource
    {
        $this->validate($request, [
            "complaintToId" => "nullable|uuid|exists:user_units,id",
            "category" => "required|in:".implode(',', SupportTicketCategory::values()),
            "subject" => "required|string|max:255",
            "priority" => "nullable|in:".implode(',', SupportTicketPriority::values()),
            "description" => "nullable|string",
            "temporaryUploadIds" => "nullable|array",
            "temporaryUploadIds.*" => "integer|exists:temporary_uploads,id",
        ]);

        [$saved, $message, $supportTicket] = $this->service->store($request);

        return SupportTicketResource::make($supportTicket)
            ->additional([
                "saved" => $saved,
                "message" => $message,
            ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(int|string $id): SupportTicketResource
    {
        [$status, $message, $supportTicket, $categories, $statuses, $priorities] = $this->service->show($id);

        return (new SupportTicketResource($supportTicket))
            ->additional([
                "status" => $status,
                "message" => $message,
                "categories" => $categories,
                "priorities" => $priorities,
                "statuses" => $statuses,
            ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int|string $id): SupportTicketResource
    {
        $this->validate($request, [
            "category" => "nullable|in:".implode(',', SupportTicketCategory::values()),
            "subject" => "nullable|string|max:255",
            "priority" => "nullable|in:".implode(',', SupportTicketPriority::values()),
            "description" => "nullable|string",
            "status" => "nullable|in:".implode(',', SupportTicketStatus::values()),
        ]);

        [$saved, $message, $supportTicket] = $this->service->update($request, $id);

        return SupportTicketResource::make($supportTicket)
            ->additional([
                "saved" => $saved,
                "message" => $message,
            ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int|string $id): JsonResponse
    {
        $ids = explode(",", $id);

        $deleted = SupportTicket::whereIn('id', $ids)->delete();

        return response()->json([
            "deleted" => $deleted > 0,
            "message" => count($ids) > 1
                ? "Support Tickets Deleted Successfully"
                : "Support Ticket Deleted Successfully",
        ], 200);
    }
}
