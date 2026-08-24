<?php

namespace App\Http\Controllers\Mail;

use App\Http\Controllers\Controller;
use App\Http\Resources\MailLabelResource;
use App\Http\Services\MailLabelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MailLabelController extends Controller
{
    public function __construct(protected MailLabelService $service) {}

    public function index(): AnonymousResourceCollection
    {
        [$status, $message, $labels] = $this->service->index();

        return MailLabelResource::collection($labels)
            ->additional(['status' => $status, 'message' => $message]);
    }

    public function store(Request $request): MailLabelResource
    {
        $this->validate($request, [
            'name' => 'required|string|max:100',
            'color' => 'nullable|string|max:20',
        ]);

        [$saved, $message, $label] = $this->service->store($request);

        return MailLabelResource::make($label)->additional(['saved' => $saved, 'message' => $message]);
    }

    public function update(Request $request, string $id): MailLabelResource
    {
        $this->validate($request, [
            'name' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:20',
        ]);

        [$saved, $message, $label] = $this->service->update($request, $id);

        return MailLabelResource::make($label)->additional(['saved' => $saved, 'message' => $message]);
    }

    public function destroy(string $id): JsonResponse
    {
        [$deleted, $message] = $this->service->destroy($id);

        return response()->json(['deleted' => $deleted, 'message' => $message]);
    }
}
