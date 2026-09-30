<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    /**
     * The task may be updated by the owner of its project or by its assignee.
     */
    public function update(User $user, Task $task): bool
    {
        return $user->id === $task->project->owner_id
            || $user->id === $task->assignee_id;
    }

    /**
     * Only the owner of the task's project may delete it.
     */
    public function delete(User $user, Task $task): bool
    {
        return $user->id === $task->project->owner_id;
    }
}
