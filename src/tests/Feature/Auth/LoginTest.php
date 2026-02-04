<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_can_login_with_correct_credentials(): void
    {
        // Arrange
        $user = User::factory()->create([
            'password' => 'password123',
        ]);

        // Act
        $response = $this->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'password123',
        ]);

        // Assert
        $response
            ->assertOk()
            ->assertJsonStructure([
                'token',
                'user' => [
                    'id',
                    'email',
                ],
            ]);
    }

    #[Test]
    public function user_cannot_login_with_invalid_password(): void
    {
        $user = User::factory()->create([
            'password' => 'correct-password',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function guest_cannot_access_me_endpoint(): void
    {
        $this->getJson('/api/auth/me')
            ->assertUnauthorized();
    }

    #[Test]
    public function authenticated_user_can_access_me_endpoint(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJson([
                'user' => [
                    'id'    => $user->id,
                    'email' => $user->email,
                ],
            ]);
    }

    #[Test]
    public function logout_deletes_only_current_token(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('mobile');

        $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken)
            ->postJson('/api/auth/logout')
            ->assertOk();

        $this->assertCount(0, $user->tokens);
    }
}
