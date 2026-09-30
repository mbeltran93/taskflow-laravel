<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_anyone_can_list_projects_without_a_token(): void
    {
        Project::factory()->count(3)->create();

        $response = $this->getJson('/api/projects');

        $response->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_anyone_can_view_a_single_project_without_a_token(): void
    {
        $project = Project::factory()->create();

        $response = $this->getJson("/api/projects/{$project->id}");

        $response->assertOk()->assertJsonPath('id', $project->id);
    }

    public function test_creating_a_project_requires_a_token(): void
    {
        $response = $this->postJson('/api/projects', [
            'name' => 'New project',
        ]);

        $response->assertStatus(401);
    }

    public function test_an_authenticated_user_can_create_a_project(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/projects', [
            'name' => 'New project',
            'description' => 'A project created in a test.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('name', 'New project')
            ->assertJsonPath('ownerId', $user->id);

        $this->assertDatabaseHas('projects', [
            'name' => 'New project',
            'owner_id' => $user->id,
        ]);
    }

    public function test_creating_a_project_requires_a_name(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/projects', []);

        $response->assertStatus(422)->assertJsonValidationErrors(['name']);
    }

    public function test_the_owner_can_update_their_project(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($owner, 'sanctum')->putJson("/api/projects/{$project->id}", [
            'name' => 'Renamed project',
        ]);

        $response->assertOk()->assertJsonPath('name', 'Renamed project');
    }

    public function test_another_user_cannot_update_someone_elses_project(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($intruder, 'sanctum')->putJson("/api/projects/{$project->id}", [
            'name' => 'Hijacked project',
        ]);

        $response->assertStatus(403);
    }

    public function test_the_owner_can_delete_their_project(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($owner, 'sanctum')->deleteJson("/api/projects/{$project->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_another_user_cannot_delete_someone_elses_project(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($intruder, 'sanctum')->deleteJson("/api/projects/{$project->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('projects', ['id' => $project->id]);
    }
}
