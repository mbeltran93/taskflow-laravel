<?php

namespace Tests\Unit\Policies;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Policies\TaskPolicy;
use Tests\TestCase;

/**
 * Pure unit tests for TaskPolicy: in-memory models only, no database and no
 * HTTP layer involved (unlike tests/Feature, which exercises the same rules
 * through the actual endpoints). The task->project relation is set directly
 * with setRelation() so evaluating the policy never triggers a query.
 */
class TaskPolicyTest extends TestCase
{
    private TaskPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new TaskPolicy();
    }

    private function userWithId(int $id): User
    {
        $user = new User();
        $user->id = $id;

        return $user;
    }

    private function taskForProjectOwnedBy(int $ownerId, ?int $assigneeId): Task
    {
        $project = new Project();
        $project->owner_id = $ownerId;

        $task = new Task();
        $task->assignee_id = $assigneeId;
        $task->setRelation('project', $project);

        return $task;
    }

    public function test_project_owner_can_update_the_task(): void
    {
        $owner = $this->userWithId(1);
        $task = $this->taskForProjectOwnedBy(ownerId: 1, assigneeId: null);

        $this->assertTrue($this->policy->update($owner, $task));
    }

    public function test_assignee_can_update_the_task(): void
    {
        $assignee = $this->userWithId(2);
        $task = $this->taskForProjectOwnedBy(ownerId: 1, assigneeId: 2);

        $this->assertTrue($this->policy->update($assignee, $task));
    }

    public function test_unrelated_user_cannot_update_the_task(): void
    {
        $intruder = $this->userWithId(3);
        $task = $this->taskForProjectOwnedBy(ownerId: 1, assigneeId: 2);

        $this->assertFalse($this->policy->update($intruder, $task));
    }

    public function test_project_owner_can_delete_the_task(): void
    {
        $owner = $this->userWithId(1);
        $task = $this->taskForProjectOwnedBy(ownerId: 1, assigneeId: 2);

        $this->assertTrue($this->policy->delete($owner, $task));
    }

    public function test_assignee_cannot_delete_the_task(): void
    {
        $assignee = $this->userWithId(2);
        $task = $this->taskForProjectOwnedBy(ownerId: 1, assigneeId: 2);

        $this->assertFalse($this->policy->delete($assignee, $task));
    }

    public function test_unrelated_user_cannot_delete_the_task(): void
    {
        $intruder = $this->userWithId(3);
        $task = $this->taskForProjectOwnedBy(ownerId: 1, assigneeId: 2);

        $this->assertFalse($this->policy->delete($intruder, $task));
    }
}
