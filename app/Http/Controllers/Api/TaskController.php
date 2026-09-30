<?php

namespace App\Http\Controllers\Api;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Task\IndexTaskRequest;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Requests\Task\UpdateTaskStatusRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TaskController extends Controller
{
    /**
     * List tasks, optionally filtered by projectId and/or status.
     */
    public function index(IndexTaskRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $tasks = Task::query()
            ->with('assignee')
            ->when(
                $filters['projectId'] ?? null,
                fn ($query, $projectId) => $query->where('project_id', $projectId)
            )
            ->when(
                $filters['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status)
            )
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return TaskResource::collection($tasks);
    }

    /**
     * Create a new task.
     */
    public function store(StoreTaskRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['status'] ??= TaskStatus::Todo->value;

        $task = Task::query()->create($data);

        return TaskResource::make($task->load('assignee'))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a single task.
     */
    public function show(Task $task): TaskResource
    {
        return TaskResource::make($task->load('assignee'));
    }

    /**
     * Update a task. Allowed for the owner of its project or its assignee.
     */
    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        $task->update($request->validated());

        return TaskResource::make($task->fresh()->load('assignee'));
    }

    /**
     * Delete a task. Only the owner of its project may do this.
     */
    public function destroy(Task $task): JsonResponse
    {
        Gate::authorize('delete', $task);

        $task->delete();

        return response()->json(null, 204);
    }

    /**
     * Update only a task's status.
     */
    public function updateStatus(UpdateTaskStatusRequest $request, Task $task): TaskResource
    {
        $task->update(['status' => $request->validated('status')]);

        return TaskResource::make($task->fresh()->load('assignee'));
    }
}
