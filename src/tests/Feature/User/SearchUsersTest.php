<?php

namespace Tests\Feature\User;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SearchUsersTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function search_by_nickname_excludes_current_user(): void
    {
        $me = User::factory()->create(['nickname' => 'me-user']);
        $other = User::factory()->create(['nickname' => 'other-user']);

        $this->actingAs($me, 'sanctum')
            ->getJson('/api/users/search?nickname=other')
            ->assertOk()
            ->assertJsonMissing(['nickname' => 'me-user'])
            ->assertJsonPath('users.0.nickname', 'other-user');
    }
}
