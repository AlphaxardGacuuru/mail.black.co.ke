<?php

namespace App\Http\Services;

use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Models\SupportTicket;
use App\Models\TemporaryUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SupportTicketService extends Service
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = SupportTicket::with(['userUnit.user', 'userUnit.unit.property']);

        $query = $this->search($query, $request);

        $supportTickets = $query
            ->orderBy('created_at', 'DESC')
            ->paginate(20);

        return [
            true,
            $supportTickets->total()." Support Tickets Retrieved",
            $supportTickets,
            SupportTicketCategory::values(),
            SupportTicketPriority::values(),
            SupportTicketStatus::values(),
        ];
    }

    public function show($id)
    {
        $supportTicket = SupportTicket::with(['userUnit.user', 'userUnit.unit.property'])->findOrFail($id);

        return [
            true,
            "Support Ticket Retrieved Successfully",
            $supportTicket,
            SupportTicketCategory::values(),
            SupportTicketStatus::values(),
            SupportTicketPriority::values(),
        ];
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $supportTicket = new SupportTicket;
        $supportTicket->user_unit_id = auth("sanctum")->user()->currentUserUnit()->id;
        $supportTicket->complaint_to_id = $request->input('complaintToId');
        $supportTicket->category = $request->input('category');
        $supportTicket->subject = $request->input('subject');
        $supportTicket->priority = $request->input('priority');
        $supportTicket->description = $request->input('description');
        $supportTicket->status = SupportTicketStatus::OPEN->value;

        // Move temporary uploads to final support ticket folder.
        $temporaryUploadIds = collect($request->input('temporaryUploadIds', []))
            ->filter()
            ->unique()
            ->values();

        if ($temporaryUploadIds->isNotEmpty()) {
            $temporaryUploads = TemporaryUpload::whereIn('id', $temporaryUploadIds)->get();

            $paths = [];

            foreach ($temporaryUploads as $temporaryUpload) {
                $disk = $temporaryUpload->disk ?: 'public';
                $finalPath = 'support-tickets/attachments/'.basename($temporaryUpload->path);

                Storage::disk($disk)->move($temporaryUpload->path, $finalPath);
                $paths[] = $finalPath;
            }

            $supportTicket->attachments = $paths;

            TemporaryUpload::whereIn('id', $temporaryUploads->pluck('id'))->delete();
        }

        $saved = $supportTicket->save();

        return [$saved, "Support Ticket Submitted Successfully", $supportTicket];
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $supportTicket = SupportTicket::findOrFail($id);

        if ($request->filled('category')) {
            $supportTicket->category = $request->input('category');
        }

        if ($request->filled('subject')) {
            $supportTicket->subject = $request->input('subject');
        }

        if ($request->has('priority')) {
            $supportTicket->priority = $request->input('priority');
        }

        if ($request->has('description')) {
            $supportTicket->description = $request->input('description');
        }

        if ($request->filled('status')) {
            $supportTicket->status = $request->input('status');
        }

        $saved = $supportTicket->save();

        return [$saved, "Support Ticket Updated Successfully", $supportTicket];
    }

    public function destroy($id)
    {
        $supportTicket = SupportTicket::findOrFail($id);

        $deleted = DB::transaction(function () use ($supportTicket) {
            // Delete attachments from storage
            if ($supportTicket->attachments) {
                foreach ($supportTicket->attachments as $attachment) {
                    Storage::disk('public')->delete($attachment);
                }
            }

            return $supportTicket->delete();
        });

        return [$deleted, "Support Tickets Deleted Successfully", $supportTicket];
    }

    /**
     * Search / filter parameters.
     */
    public function search($query, $request)
    {
        if ($request->propertyId != "undefined") {
            $propertyIds = explode(",", $request->propertyId);

            $isSuper = in_array("All", $propertyIds);

            if (! $isSuper) {
                $query = $query->whereHas('userUnit.unit', function ($q) use ($propertyIds) {
                    $q->whereIn('property_id', $propertyIds);
                });
            }
        }

        if ($request->filled('tenantId')) {
            $query = $query->where('user_unit_id', $request->input('tenantId'));
        }

        if ($request->filled('number')) {
            $query->where('number', 'LIKE', "%{$request->number}%");
        }

        if ($request->filled('subject')) {
            $query->where('subject', 'LIKE', "%{$request->subject}%");
        }

        if ($request->filled('category')) {
            $query->where('category', 'LIKE', "%{$request->category}%");
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return $query;
    }
}
