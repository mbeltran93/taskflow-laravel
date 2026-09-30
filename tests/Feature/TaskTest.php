<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_tasks_can_be_listed_and_filtered_by_project_and_status(): void
    {
        $projectA = Project::factory()->create();
        $projectB = Project::factory()->create();

        Task::factory()->create(['project_id' => $projectA->id, 'status' => TaskStatus::Todo]);
        Task::factory()->create(['project_id' => $projectA->id, 'status' => TaskStatus::Done]);
        Task::factory()->create(['project_id' => $projectB->id, 'status' => TaskStatus::Todo]);

        $response = $this->getJson("/api/tasks?projectId={$projectA->id}&status=TODO");

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame($projectA->id, $response->json('data.0.projectId'));
        $this->assertSame('TODO', $response->json('data.0.status'));
    }

    public function test_creating_a_task_requires_a_token(): void
    {
        $project = Project::factory()->create();

        $response = $this->postJson('/api/tasks', [
            'title' => 'New task',
            'project_id' => $project->id,
        ]);

        $response->assertStatus(401);
    }

    public function test_an_authenticated_user_can_create_a_task(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/tasks', [
            'title' => 'New task',
            'project_id' => $project->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('title', 'New task')
            ->assertJsonPath('status', 'TODO');

        $this->assertDatabaseHas('tasks', [
            'title' => 'New task',
            'project_id' => $project->id,
            'status' => 'TODO',
        ]);
    }

    public function test_creating_a_task_validates_status_and_project(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/tasks', [
            'title' => 'New task',
            'project_id' => 999999,
            'status' => 'NOT_A_STATUS',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['project_id', 'status']);
    }

    public function test_the_project_owner_can_update_a_task(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $task = Task::factory()->create(['project_id' => $project->id]);

        $response = $this->actingAs($owner, 'sanctum')->putJson("/api/tasks/{$task->id}", [
            'title' => 'Updated title',
        ]);

        $response->assertOk()->assertJsonPath('title', 'Updated title');
    }

    public function test_the_assignee_can_update_the_task_status(): void
    {
        $owner = User::factory()->create();
        $assignee = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'assignee_id' => $assignee->id,
            'status' => TaskStatus::Todo,
        ]);

        $response = $this->actingAs($assignee, 'sanctum')->patchJson("/api/tasks/{$task->id}/status", [
            'status' => 'IN_PROGRESS',
        ]);

        $response->assertOk()->assertJsonPath('status', 'IN_PROGRESS');
    }

    public function test_an_unrelated_user_cannot_update_a_task(): void
    {
        $project = Project::factory()->create();
        $task = Task::factory()->create(['project_id' => $project->id, 'assignee_id' => null]);
        $stranger = User::factory()->create();

        $response = $this->actingAs($stranger, 'sanctum')->putJson("/api/tasks/{$task->id}", [
            'title' => 'Hijacked',
        ]);

        $response->assertStatus(403);
    }

    public function test_only_the_project_owner_can_delete_a_task(): void
    {
        $owner = User::factory()->create();
        $assignee = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $task = Task::factory()->create(['project_id' => $project->id, 'assignee_id' => $assignee->id]);

        // The assignee can update the task, but not delete it.
        $this->actingAs($assignee, 'sanctum')
            ->deleteJson("/api/tasks/{$task->id}")
            ->assertStatus(403);

        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/tasks/{$task->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }
}
