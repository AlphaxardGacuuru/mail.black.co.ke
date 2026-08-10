<?php

namespace App\Http\Controllers;

use App\Http\Resources\TaskCommentResource;
use App\Http\Services\TaskCommentService;
use App\Models\TaskComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TaskCommentController extends Controller
{
    public function __construct(protected TaskCommentService $service)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return $this->service->index($request);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): Response
    {
        $this->validate($request, [
            "text" => "required|string|max:10000",
            "id" => "required|string",
        ]);

        [$saved, $message, $taskComment] = $this->service->store($request);

        return response([
            "status" => $saved,
            "message" => $message,
            "data" => $taskComment,
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(int|string $id): TaskCommentResource
    {
        return $this->service->show($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int|string $id): Response
    {
        $this->validate($request, [
            "text" => "nullable|string|max:10000",
        ]);

        [$saved, $message, $taskComment] = $this->service->update($request, $id);

        return response([
            "status" => $saved,
            "message" => $message,
            "data" => $taskComment,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int|string $id): Response
    {
        [$deleted, $message, $taskComment] = $this->service->destroy($id);

        return response([
            "status" => $deleted,
            "message" => $message,
            "data" => $taskComment,
        ], 200);
    }
}
