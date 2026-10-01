<?php

namespace Tests\Unit\Policies;

use App\Models\Project;
use App\Models\User;
use App\Policies\ProjectPolicy;
use Tests\TestCase;

/**
 * Pure unit tests for ProjectPolicy: in-memory models only, no database and
 * no HTTP layer involved (unlike tests/Feature, which exercises the same
 * rules through the actual endpoints).
 */
class ProjectPolicyTest extends TestCase
{
    private ProjectPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new ProjectPolicy();
    }

    private function userWithId(int $id): User
    {
        $user = new User();
        $user->id = $id;

        return $user;
    }

    private function projectOwnedBy(int $ownerId): Project
    {
        $project = new Project();
        $project->owner_id = $ownerId;

        return $project;
    }

    public function test_owner_can_update_their_project(): void
    {
        $owner = $this->userWithId(1);
        $project = $this->projectOwnedBy(1);

        $this->assertTrue($this->policy->update($owner, $project));
    }

    public function test_non_owner_cannot_update_the_project(): void
    {
        $intruder = $this->userWithId(2);
        $project = $this->projectOwnedBy(1);

        $this->assertFalse($this->policy->update($intruder, $project));
    }

    public function test_owner_can_delete_their_project(): void
    {
        $owner = $this->userWithId(1);
        $project = $this->projectOwnedBy(1);

        $this->assertTrue($this->policy->delete($owner, $project));
    }

    public function test_non_owner_cannot_delete_the_project(): void
    {
        $intruder = $this->userWithId(2);
        $project = $this->projectOwnedBy(1);

        $this->assertFalse($this->policy->delete($intruder, $project));
    }
}
