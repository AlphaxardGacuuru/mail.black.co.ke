<?php

namespace App\Http\Controllers;

use App\Http\Resources\StoryResource;
use App\Http\Services\StoryService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class StoryController extends Controller
{
    public function __construct(protected StoryService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return StoryResource::collection($this->service->index($request->user()));
    }

    public function show(Request $request, string $id): StoryResource
    {
        return StoryResource::make($this->service->show($request->user(), $id));
    }

    public function seen(Request $request, string $id): Response
    {
        return response([
            'message' => 'Story Seen',
            'data' => $this->service->seen($request->user(), $id),
        ], 200);
    }
}
