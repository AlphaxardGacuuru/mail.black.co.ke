<?php

namespace App\Http\Services;

use App\Http\Resources\StageResource;
use App\Models\Stage;

class StageService extends Service
{
    /*
     * Get All Stages
     */
    public function index($request)
    {
        $query = Stage::query();

        $query = $this->stageSearch($query, $request);

        $taskStages = collect([]);

        $stages = $query
            ->orderBy("position", "ASC")
            ->get()
            ->map(function ($stage) use ($taskStages, $request) {
                // Fetch the unique tasks for each stage
                $taskStageQuery = $this->taskSearch($stage->taskStages(), $request);

                $tasks = $taskStageQuery
                    ->orderBy("id", "asc")
                    ->get()
                    ->map(function ($taskStage) use ($taskStages) {
                        $taskStages->push($taskStage);

                        return $taskStage->task;
                    });

                $stage->uniqueTasks = $tasks;

                return $stage;
            });

        // Get The latest Task Stages
        $taskStages = $taskStages
            ->sortByDesc("id")
            ->values()
            ->reduce(function ($acc, $taskStage) {
                // Check if the accumulator already contains the taskStage with the same id
                if ($acc->doesntContain("task_id", $taskStage->task_id)) {
                    $acc->push($taskStage);
                }

                return $acc;
            }, collect([]));

        // Mark old Task Stages
        $stages = $stages->map(function ($stage) use ($taskStages) {
            $uniqueTasks = $stage
                ->taskStages
                ->map(function ($taskStage) use ($taskStages) {
                    // Mark task as new or not based on its presence in taskStages
                    if ($taskStages->doesntContain("id", $taskStage->id)) {
                        $taskStage->task->new = false;
                    } else {
                        $taskStage->task->new = true;
                    }

                    return $taskStage->task;
                })
                ->filter(fn ($task) => $task->new);

            $stage->test = $taskStages;
            $stage->uniqueTasks = $uniqueTasks;

            return $stage;
        });

        return [true, "Stages Retrieved Successfully", $stages];
    }

    /*
     * Get One Stage
     */
    public function show($id)
    {
        $stage = Stage::findOrFail($id);

        return [true, "Stage Retrieved Successfully", new StageResource($stage)];
    }

    /*
     * Store Stage
     */
    public function store($request)
    {
        $stage = new Stage;
        $stage->property_id = $request->propertyId;
        $stage->name = $request->name;
        $stage->position = $request->position;
        $stage->created_by = $this->id;
        $saved = $stage->save();

        $message = $stage->name." Created Successfully";

        return [$saved, $message, $stage];
    }

    /*
     * Update Stage
     */
    public function update($request, $id)
    {
        $stage = Stage::find($id);

        if ($request->filled("name")) {
            $stage->name = $request->name;
        }

        if ($request->filled("position")) {
            $stage->position = $request->position;
        }

        $saved = $stage->save();

        $message = $stage->name." Updated Successfully";

        return [$saved, $message, $stage];
    }

    /*
     * Delete Stage
     */
    public function destroy($id)
    {
        $stage = Stage::findOrFail($id);

        $deleted = $stage->delete();

        $message = $stage->name." Deleted Successfully";

        return [$deleted, $message, $stage];
    }

    /*
     * Handle Stage Search
     */
    public function stageSearch($query, $request)
    {
        if ($request->propertyId != "undefined") {
            $propertyIds = explode(",", $request->propertyId);

            $query = $query->whereIn("property_id", $propertyIds);
        }

        return $query;
    }

    /*
     * Handle Task Search
     */
    public function taskSearch($query, $request)
    {
        if ($request->propertyId != "undefined") {
            $propertyIds = explode(",", $request->propertyId);

            $query = $query->whereHas("stage", function ($query) use ($propertyIds) {
                $query->whereIn("property_id", $propertyIds);
            });
        }

        if ($request->filled("number")) {
            $query->whereHas("task", function ($query) use ($request) {
                $query->where("number", "LIKE", "%".$request->input("number")."%");
            });
        }

        if ($request->filled("title")) {
            $query->whereHas("task", function ($query) use ($request) {
                $query->where("title", "LIKE", "%".$request->input("title")."%");
            });
        }

        if ($request->filled("priority")) {
            $query->whereHas("task", function ($query) use ($request) {
                $query->where("priority", $request->input("priority"));
            });
        }

        if ($request->filled("staffId")) {
            $query->whereHas("task", function ($query) use ($request) {
                $query->where("assigned_to", $request->input("staffId"));
            });
        }

        return $query;
    }
}
