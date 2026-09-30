<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_log_in_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'alice@example.com',
            'password' => bcrypt('correct-password'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);

        $response->assertOk()->assertJsonStructure(['token']);
        $this->assertIsString($response->json('token'));
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'alice@example.com',
            'password' => bcrypt('correct-password'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'alice@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
    }

    public function test_login_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_authenticated_user_can_fetch_their_own_profile(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/me');

        $response->assertOk()->assertJsonPath('id', $user->id);
    }
}
