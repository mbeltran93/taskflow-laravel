<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_be_listed_without_a_token(): void
    {
        User::factory()->count(2)->create();

        $response = $this->getJson('/api/users');

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_a_single_user_can_be_fetched_without_a_token(): void
    {
        $user = User::factory()->create();

        $response = $this->getJson("/api/users/{$user->id}");

        $response->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('email', $user->email)
            ->assertJsonMissing(['password']);
    }

    public function test_fetching_an_unknown_user_returns_404(): void
    {
        $response = $this->getJson('/api/users/999999');

        $response->assertStatus(404);
    }
}
