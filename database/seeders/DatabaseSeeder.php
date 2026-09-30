<?php

namespace Database\Seeders;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with a small, deterministic demo dataset.
     */
    public function run(): void
    {
        $alice = User::factory()->create([
            'name' => 'Alice Owner',
            'email' => 'alice@taskflow.test',
            'password' => Hash::make('password'),
        ]);

        $bob = User::factory()->create([
            'name' => 'Bob Developer',
            'email' => 'bob@taskflow.test',
            'password' => Hash::make('password'),
        ]);

        $carol = User::factory()->create([
            'name' => 'Carol Designer',
            'email' => 'carol@taskflow.test',
            'password' => Hash::make('password'),
        ]);

        $website = Project::factory()->create([
            'name' => 'Website Revamp',
            'description' => 'Redesign the marketing site and migrate the blog.',
            'owner_id' => $alice->id,
        ]);

        $mobile = Project::factory()->create([
            'name' => 'Mobile App',
            'description' => 'Native iOS/Android client for TaskFlow.',
            'owner_id' => $bob->id,
        ]);

        Task::factory()->create([
            'title' => 'Set up CI pipeline',
            'description' => 'Configure GitHub Actions to run the test suite on every push.',
            'status' => TaskStatus::Done,
            'project_id' => $website->id,
            'assignee_id' => $bob->id,
            'due_date' => now()->subDays(3),
        ]);

        Task::factory()->create([
            'title' => 'Design the new landing page',
            'description' => 'Deliver high-fidelity mockups for the homepage.',
            'status' => TaskStatus::InProgress,
            'project_id' => $website->id,
            'assignee_id' => $carol->id,
            'due_date' => now()->addDays(5),
        ]);

        Task::factory()->create([
            'title' => 'Write API documentation',
            'status' => TaskStatus::Todo,
            'project_id' => $website->id,
            'assignee_id' => null,
            'due_date' => now()->addDays(10),
        ]);

        Task::factory()->create([
            'title' => 'Implement push notifications',
            'description' => 'Add support for task reminders via push notifications.',
            'status' => TaskStatus::Todo,
            'project_id' => $mobile->id,
            'assignee_id' => $bob->id,
            'due_date' => now()->addDays(14),
        ]);

        Task::factory()->create([
            'title' => 'Prepare app store screenshots',
            'status' => TaskStatus::InProgress,
            'project_id' => $mobile->id,
            'assignee_id' => $carol->id,
            'due_date' => null,
        ]);

        // A handful of extra random tasks so pagination/filtering has more to work with.
        Task::factory(5)->create([
            'project_id' => fn () => fake()->randomElement([$website->id, $mobile->id]),
        ]);
    }
}
