<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SecurityAndRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_responses_include_hardening_security_headers(): void
    {
        $response = $this->get('/login');

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'")
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_unauthenticated_users_cannot_access_protected_routes(): void
    {
        $this->get('/generations')->assertRedirect(route('login'));
        $this->get('/generations/status')->assertRedirect(route('login'));
        $this->get('/credits')->assertRedirect(route('login'));
        $this->get('/admin/reports')->assertRedirect(route('login'));
    }

    public function test_login_endpoint_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post('/login', [
                'email' => 'nobody@example.com',
                'password' => 'wrong-password',
            ])->assertRedirect();
        }

        $this->post('/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_generation_submission_endpoint_is_rate_limited_per_user(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 15; $i++) {
            $this->actingAs($user)
                ->post('/generations', ['prompt' => ''])
                ->assertSessionHasErrors('prompt');
        }

        $this->actingAs($user)
            ->post('/generations', ['prompt' => ''])
            ->assertStatus(429);
    }
}
