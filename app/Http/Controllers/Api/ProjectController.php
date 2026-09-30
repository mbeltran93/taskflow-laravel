<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller
{
    /**
     * List every project, with its owner eager loaded.
     */
    public function index(): AnonymousResourceCollection
    {
        return ProjectResource::collection(
            Project::query()
                ->with('owner')
                ->withCount('tasks')
                ->orderBy('id')
                ->paginate(15)
        );
    }

    /**
     * Create a new project. The authenticated user becomes its owner.
     */
    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = Project::query()->create([
            ...$request->validated(),
            'owner_id' => $request->user()->id,
        ]);

        return ProjectResource::make($project->load('owner'))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a single project.
     */
    public function show(Project $project): ProjectResource
    {
        return ProjectResource::make($project->load('owner')->loadCount('tasks'));
    }

    /**
     * Update a project. Only its owner may do this.
     */
    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        $project->update($request->validated());

        return ProjectResource::make($project->fresh()->load('owner'));
    }

    /**
     * Delete a project (and cascade-delete its tasks). Only its owner may do this.
     */
    public function destroy(Project $project): JsonResponse
    {
        Gate::authorize('delete', $project);

        $project->delete();

        return response()->json(null, 204);
    }
}
