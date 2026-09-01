<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\MailgunEventResource;
use App\Models\MailgunEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminWebhookController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $q = trim((string) $request->input('q', ''));
        $event = $request->input('event');
        $status = $request->input('status');
        $from = $request->input('from');
        $to = $request->input('to');
        $perPage = min((int) $request->input('perPage', 20), 100) ?: 20;

        $query = MailgunEvent::query()->with('mailMessage:id,subject');

        if ($q !== '') {
            $query->where(function ($subQuery) use ($q) {
                $subQuery->where('provider_event_id', 'like', "%{$q}%")
                    ->orWhere('event', 'like', "%{$q}%")
                    ->orWhere('status', 'like', "%{$q}%")
                    ->orWhereRaw('CAST(payload AS CHAR) LIKE ?', ["%{$q}%"])
                    ->orWhereHas('mailMessage', fn ($messageQuery) => $messageQuery->where('subject', 'like', "%{$q}%"));
            });
        }

        if ($event) {
            $query->where('event', $event);
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($from) {
            $query->where('occurred_at', '>=', $from);
        }

        if ($to) {
            $query->where('occurred_at', '<=', $to);
        }

        $events = $query->orderByDesc('occurred_at')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        return MailgunEventResource::collection($events);
    }
}
