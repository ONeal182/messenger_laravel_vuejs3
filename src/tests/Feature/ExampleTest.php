<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    #[Test]
    public function debug_environment(): void
    {
        dump([
            'APP_ENV' => app()->environment(),
            'DB_DATABASE' => config('database.connections.mysql.database'),
            'DB_CONNECTION' => config('database.default'),
        ]);
    }
}
