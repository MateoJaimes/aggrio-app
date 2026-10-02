<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_public_landing_links_to_the_angular_login(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee(rtrim((string) config('app.frontend_url'), '/').'/auth/login');
    }
}
