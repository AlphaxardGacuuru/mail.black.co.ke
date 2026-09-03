<?php

namespace App\Http\Controllers\Mail;

use App\Enums\MailFolder;
use App\Http\Controllers\Controller;
use App\Http\Resources\MailThreadResource;
use App\Http\Services\MailThreadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class MailThreadController extends Controller
{
    public function __construct(protected MailThreadService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        [$status, $message, $threads] = $this->service->index($request);

        return MailThreadResource::collection($threads)
            ->additional([
                'status' => $status,
                'message' => $message
            ]);
    }

    public function show(string $id): MailThreadResource
    {
        [$status, $message, $thread] = $this->service->show($id);

        return MailThreadResource::make($thread)
            ->additional([
                'status' => $status,
                'message' => $message
            ]);
    }

    public function store(): JsonResponse
    {
        return response()->json([
            'message' => 'Create threads by sending a message.',
        ], 405);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'folder' => ['required_without_all:isStarred,isRead,restore', 'string', Rule::in([
                MailFolder::ARCHIVE->value,
                MailFolder::TRASH->value,
                MailFolder::INBOX->value,
            ])],
            'restore' => ['sometimes', 'boolean'],
            'isStarred' => ['sometimes', 'boolean'],
            'isRead' => ['sometimes', 'boolean'],
        ]);

        return $this->respondWith($this->service->update(
            $id,
            $data['folder'] ?? null,
            $data['isStarred'] ?? null,
            $data['isRead'] ?? null,
            $data['restore'] ?? false,
        ));
    }

    public function destroy(string $id): JsonResponse
    {
        [$deleted, $message] = $this->service->destroy($id);

        return response()->json(['deleted' => $deleted, 'message' => $message]);
    }

    public function attachLabel(Request $request, string $id): JsonResponse
    {
        $this->validate($request, ['labelId' => 'required|uuid|exists:mail_labels,id']);

        return $this->respondWith($this->service->attachLabel($id, $request->input('labelId')));
    }

    public function detachLabel(string $id, string $labelId): JsonResponse
    {
        return $this->respondWith($this->service->detachLabel($id, $labelId));
    }

    protected function respondWith(array $result): JsonResponse
    {
        [$status, $message, $thread] = $result;

        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => $thread ? MailThreadResource::make($thread) : null,
        ]);
    }
}
