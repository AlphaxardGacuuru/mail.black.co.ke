<?php

namespace App\Http\Controllers\Mail;

use App\Http\Controllers\Controller;
use App\Http\Resources\MailThreadResource;
use App\Http\Services\MailThreadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MailThreadController extends Controller
{
    public function __construct(protected MailThreadService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        [$status, $message, $threads] = $this->service->index($request);

        return MailThreadResource::collection($threads)
            ->additional(['status' => $status, 'message' => $message]);
    }

    public function show(string $id): MailThreadResource
    {
        [$status, $message, $thread] = $this->service->show($id);

        return MailThreadResource::make($thread)
            ->additional(['status' => $status, 'message' => $message]);
    }

    public function star(string $id): JsonResponse
    {
        return $this->respondWith($this->service->star($id));
    }

    public function unstar(string $id): JsonResponse
    {
        return $this->respondWith($this->service->unstar($id));
    }

    public function archive(string $id): JsonResponse
    {
        return $this->respondWith($this->service->archive($id));
    }

    public function unarchive(string $id): JsonResponse
    {
        return $this->respondWith($this->service->unarchive($id));
    }

    public function trash(string $id): JsonResponse
    {
        return $this->respondWith($this->service->trash($id));
    }

    public function restore(string $id): JsonResponse
    {
        return $this->respondWith($this->service->restore($id));
    }

    public function markRead(string $id): JsonResponse
    {
        return $this->respondWith($this->service->markRead($id));
    }

    public function markUnread(string $id): JsonResponse
    {
        return $this->respondWith($this->service->markUnread($id));
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
