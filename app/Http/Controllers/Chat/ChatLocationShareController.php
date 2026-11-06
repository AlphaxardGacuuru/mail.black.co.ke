<?php

namespace App\Http\Controllers\Chat;

use App\Events\LocationShareStartedEvent;
use App\Events\LocationShareStoppedEvent;
use App\Http\Controllers\Controller;
use App\Http\Services\ChatLocationShareService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatLocationShareController extends Controller
{
    public function __construct(protected ChatLocationShareService $service) {}

    public function start(Request $request, string $id): JsonResponse
    {
        [$status, $message, $conversation] = $this->service->start($id);

        LocationShareStartedEvent::dispatchIf($status, $conversation, $request->user()->id);

        return response()->json([
            'status' => $status,
            'message' => $message,
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->validate($request, [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracyMeters' => 'nullable|numeric|min:0',
        ]);

        [$status, $message] = $this->service->updatePosition($id, $request->only([
            'latitude', 'longitude', 'accuracyMeters',
        ]));

        return response()->json([
            'status' => $status,
            'message' => $message,
        ]);
    }

    public function stop(Request $request, string $id): JsonResponse
    {
        [$status, $message, $conversation] = $this->service->stop($id);

        LocationShareStoppedEvent::dispatchIf($status, $conversation, $request->user()->id);

        return response()->json([
            'status' => $status,
            'message' => $message,
        ]);
    }
}
