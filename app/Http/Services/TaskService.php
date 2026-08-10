<?php

namespace App\Http\Services;

use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Models\TaskStage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TaskService extends Service
{
    /*
     * Get All Tasks
     */
    public function index($request)
    {
        $tasksQuery = new Task;

        $tasksQuery = $this->search($tasksQuery, $request);

        $tasks = $tasksQuery
            ->paginate(20);

        return TaskResource::collection($tasks);
    }

    /*
     * Get One Task
     */
    public function show($id)
    {
        $task = Task::findOrFail($id);

        return new TaskResource($task);
    }

    /*
     * Store Task
     */
    public function store($request)
    {
        $task = new Task;
        $task->title = $request->title;
        $task->description = $request->description;
        $task->assigned_to = $request->assignedTo;
        $task->start_date = $request->startDate;
        $task->end_date = $request->endDate;
        $task->priority = $request->priority;
        $task->created_by = $this->id;

        $saved = DB::transaction(function () use ($task, $request) {
            $task->save();

            $taskStage = new TaskStage;
            $taskStage->stage_id = $request->stageId;
            $taskStage->task_id = $task->id;
            $taskStage->created_by = $this->id;

            return $taskStage->save();
        });

        $message = "Task ".$task->code." Created Successfully";

        return [$saved, $message, $task];
    }

    /*
     * Update Task
     */
    public function update($request, $id)
    {
        $task = Task::find($id);

        if ($request->filled("code")) {
            $task->code = $request->code;
        }

        if ($request->filled("title")) {
            $task->title = $request->title;
        }

        if ($request->filled("description")) {
            $task->description = $request->description;
        }

        if ($request->filled("assignedTo")) {
            $task->assigned_to = $request->assignedTo;
        }

        if ($request->filled("startDate")) {
            $task->start_date = $request->startDate;
        }

        if ($request->filled("endDate")) {
            $task->end_date = $request->endDate;
        }

        if ($request->filled("priority")) {
            $task->priority = $request->priority;
        }

        if ($request->filled("stageId")) {
            $taskStage = new TaskStage;
            $taskStage->stage_id = $request->stageId;
            $taskStage->task_id = $id;
            $taskStage->created_by = $this->id;
            $taskStage->save();
        }

        $saved = $task->save();

        $message = "Task ".$task->code." Updated Successfully";

        return [$saved, $message, $task];
    }

    /*
     * Delete Task
     */
    public function destroy($id)
    {
        $task = Task::findOrFail($id);

        $deleted = $task->delete();

        $message = $task->code." Deleted Successfully";

        return [$deleted, $message, $task];
    }

    /*
     * Reorder Tasks
     */
    public function reorder($request)
    {
        DB::transaction(function () use ($request) {
            foreach ($request->idsAndPositions as $idAndPosition) {
                $task = Task::find($idAndPosition["id"]);
                $task->position = $idAndPosition["position"];
                $task->created_by = $this->id;
                $task->save();
            }
        });

        return ["Saved", "Task Reordered Successfully", ""];
    }

    /*
     * Handle Search
     */
    public function search($query, $request)
    {
        if ($request->filled("number")) {
            $query = $query->where("number", "LIKE", "%".$request->number."%");
        }

        if ($request->filled("title")) {
            $query = $query->where("title", "LIKE", "%".$request->title."%");
        }

        if ($request->filled("createdBy")) {
            $query = $query->where("created_by", $request->createdBy);
        }

        $startMonth = $request->input("startMonth");
        $endMonth = $request->input("endMonth");
        $startYear = $request->input("startYear");
        $endYear = $request->input("endYear");

        // Build start date filter
        if ($request->filled("startMonth") || $request->filled("startYear")) {
            $year = $startYear ?? date('Y');
            $month = $startMonth ?? 1;
            $startDate = Carbon::create($year, $month, 1)->startOfMonth();
            $query = $query->where("start_date", ">=", $startDate);
        }

        // Build end date filter
        if ($request->filled("endMonth") || $request->filled("endYear")) {
            $year = $endYear ?? date('Y');
            $month = $endMonth ?? 12;
            $endDate = Carbon::create($year, $month, 1)->endOfMonth();
            $query = $query->where("end_date", "<=", $endDate);
        }

        return $query;
    }
}
