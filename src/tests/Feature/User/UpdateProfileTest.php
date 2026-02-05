<?php

namespace Tests\Feature\User;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpdateProfileTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function nickname_must_be_unique(): void
    {
        $me = User::factory()->create(['nickname' => 'me']);
        $taken = User::factory()->create(['nickname' => 'taken']);

        $this->actingAs($me, 'sanctum')
            ->putJson('/api/profile', ['nickname' => 'taken'])
            ->assertStatus(422);
    }

    #[Test]
    public function user_can_update_profile_fields(): void
    {
        $me = User::factory()->create(['nickname' => 'me']);

        $this->actingAs($me, 'sanctum')
            ->putJson('/api/profile', [
                'name' => 'John',
                'last_name' => 'Doe',
                'nickname' => 'johnny',
            ])
            ->assertOk()
            ->assertJsonPath('user.nickname', 'johnny')
            ->assertJsonPath('user.name', 'John')
            ->assertJsonPath('user.last_name', 'Doe');
    }
}
