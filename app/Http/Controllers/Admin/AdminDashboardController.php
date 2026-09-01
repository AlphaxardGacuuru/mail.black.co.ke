<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MailStatus;
use App\Http\Controllers\Controller;
use App\Models\MailgunEvent;
use App\Models\MailMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $statusCounts = MailMessage::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $totals = [
            'mailsSent' => (int) ($statusCounts[MailStatus::SENT->value] ?? 0),
            'mailsDelivered' => (int) ($statusCounts[MailStatus::DELIVERED->value] ?? 0),
            'mailsFailed' => (int) ($statusCounts[MailStatus::PERMANENT_FAILED->value] ?? 0)
                + (int) ($statusCounts[MailStatus::TEMPORARY_FAILED->value] ?? 0)
                + (int) ($statusCounts[MailStatus::FAILED->value] ?? 0),
            'mailsBounced' => (int) ($statusCounts[MailStatus::BOUNCED->value] ?? 0),
            'mailsQueued' => (int) ($statusCounts[MailStatus::QUEUED->value] ?? 0),
            'mailsReceived' => (int) MailMessage::query()->where('direction', 'inbound')->count(),
            'totalUsers' => (int) User::query()->count(),
            'totalWebhookEvents' => (int) MailgunEvent::query()->count(),
            'webhookEventsLast24h' => (int) MailgunEvent::query()
                ->where('created_at', '>=', now()->subDay())
                ->count(),
        ];

        $statusBreakdown = collect(MailStatus::cases())
            ->map(fn (MailStatus $status) => [
                'status' => $status->value,
                'count' => (int) ($statusCounts[$status->value] ?? 0),
            ])
            ->values();

        $days = collect(range(13, 0))
            ->map(fn (int $offset) => now()->subDays($offset)->toDateString());

        $dailyRows = MailMessage::query()
            ->where('direction', 'outbound')
            ->where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->selectRaw('DATE(created_at) as date, status, count(*) as aggregate')
            ->groupBy('date', 'status')
            ->get();

        $failedStatuses = [
            MailStatus::FAILED->value,
            MailStatus::PERMANENT_FAILED->value,
            MailStatus::TEMPORARY_FAILED->value,
            MailStatus::BOUNCED->value,
        ];

        $dailyVolume = $days->map(function (string $date) use ($dailyRows, $failedStatuses) {
            $rowsForDate = $dailyRows->filter(fn ($row) => $row->date === $date);

            return [
                'date' => $date,
                'sent' => (int) $rowsForDate
                    ->where('status', MailStatus::SENT->value)
                    ->sum('aggregate'),
                'failed' => (int) $rowsForDate
                    ->filter(fn ($row) => in_array($row->status, $failedStatuses, true))
                    ->sum('aggregate'),
            ];
        })->values();

        $recentFailures = MailMessage::query()
            ->whereIn('status', $failedStatuses)
            ->latest('created_at')
            ->limit(5)
            ->get(['id', 'subject', 'to', 'status', 'error_message', 'created_at'])
            ->map(fn (MailMessage $message) => [
                'id' => $message->id,
                'subject' => $message->subject,
                'to' => collect($message->to ?? [])->pluck('address')->implode(', '),
                'status' => $message->status,
                'errorMessage' => $message->error_message,
                'createdAt' => $message->created_at,
            ])
            ->values();

        return response()->json([
            'data' => [
                'totals' => $totals,
                'statusBreakdown' => $statusBreakdown,
                'dailyVolume' => $dailyVolume,
                'recentFailures' => $recentFailures,
            ],
        ]);
    }
}
