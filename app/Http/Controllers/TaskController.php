<?php

namespace App\Http\Controllers;

use App\Http\Resources\TaskResource;
use App\Http\Services\TaskService;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TaskController extends Controller
{
    public function __construct(protected TaskService $service)
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
            "title" => "required|string",
            "description" => "nullable|string|max:10000",
            "assignedTo" => "required|string",
            "startDate" => "required|date",
            "endDate" => "required|date|after_or_equal:startDate",
            "priority" => "required|string",
            "unitId" => "nullable|exists:units,id",
            "stageId" => "required|exists:stages,id",
        ]);

        [$saved, $message, $task] = $this->service->store($request);

        return response([
            "status" => $saved,
            "message" => $message,
            "data" => $task,
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(int|string $id): TaskResource
    {
        return $this->service->show($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int|string $id): Response
    {
        $this->validate($request, [
            "title" => "nullable|string",
            "description" => "nullable|string|max:10000",
            "assignedTo" => "nullable|string",
            "startDate" => "nullable|date",
            "endDate" => "nullable|date|after_or_equal:startDate",
            "priority" => "nullable|string",
            "unitId" => "nullable|exists:units,id",
            "projectId" => "nullable|string",
            "stageId" => "nullable|exists:stages,id",
        ]);

        [$saved, $message, $task] = $this->service->update($request, $id);

        return response([
            "status" => $saved,
            "message" => $message,
            "data" => $task,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int|string $id): Response
    {
        [$deleted, $message, $task] = $this->service->destroy($id);

        return response([
            "status" => $deleted,
            "message" => $message,
            "data" => $task,
        ], 200);
    }

    /*
    * Reorder Tasks
    */
    public function reorder(Request $request, int|string $id): Response
    {
        [$saved, $message, $data] = $this->service->reorder($request);

        return response([
            "status" => $saved,
            "message" => $message,
            "data" => $data,
        ], 200);
    }
}
