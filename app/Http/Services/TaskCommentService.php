<?php

namespace App\Http\Services;

use App\Http\Resources\TaskCommentResource;
use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Support\Facades\DB;

class TaskCommentService extends Service
{
    /*
     * Get All Task Comments
     */
    public function index($request)
    {
        $taskCommentsQuery = new TaskComment;

        $taskCommentsQuery = $this->search($taskCommentsQuery, $request);

        $taskComments = $taskCommentsQuery
            ->get();

        return TaskCommentResource::collection($taskComments);
    }

    /*
     * Get One Task Comment
     */
    public function show($id)
    {
        $taskComment = TaskComment::findOrFail($id);

        return new TaskCommentResource($taskComment);
    }

    /*
     * Store Task Comment
     */
    public function store($request)
    {
        $taskComment = new TaskComment;
        $taskComment->text = $request->text;
        $taskComment->task_id = $request->id;
        $taskComment->created_by = $this->id;

        $saved = DB::transaction(function () use ($taskComment) {
            $taskComment->save();

            $task = Task::find($taskComment->task_id);
            $task->increment("total_comments");

            return $task->save();
        });

        $message = "Comment created successfully";

        return [$saved, $message, $taskComment];
    }

    /*
     * Update Task Comment
     */
    public function update($request, $id)
    {
        $taskComment = TaskComment::find($id);

        if ($request->filled("text")) {
            $taskComment->text = $request->text;
        }

        $saved = $taskComment->save();

        $message = "Comment updated successfully";

        return [$saved, $message, $taskComment];
    }

    /*
     * Delete Task Comment
     */
    public function destroy($id)
    {
        $taskComment = TaskComment::findOrFail($id);

        $deleted = $taskComment->delete();

        $message = "Comment deleted successfully";

        return [$deleted, $message, $taskComment];
    }

    /*
     * Handle Search
     */
    public function search($query, $request)
    {
        if ($request->filled("taskId")) {
            $query = $query
                ->where("task_id", $request->taskId);
        }

        return $query;
    }
}
