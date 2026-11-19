<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_404_renders_custom_inertia_error_page_with_login_cta(): void
    {
        $response = $this->get('/this-page-does-not-exist');

        $response->assertStatus(404);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Errors/ErrorPage')
            ->where('status', 404)
            ->where('title', 'Page Not Found')
            ->where('primaryCta.label', 'Go to Sign In')
            ->where('primaryCta.href', route('login'))
            ->where('secondaryCta.label', 'Create Account')
            ->where('secondaryCta.href', route('register'))
            ->where('canRetry', false));
    }

    public function test_authenticated_404_renders_custom_inertia_error_page_with_studio_cta(): void
    {
        $user = User::factory()->create([
            'password' => 'secret-pass-123',
        ]);

        // Authenticate via real HTTP login flow so session middleware (not actingAs) resolves the user.
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'secret-pass-123',
        ])->assertRedirect(route('generations.index'));

        $this->app['auth']->forgetGuards();

        $response = $this->get('/unknown-studio-route');

        $response->assertStatus(404);
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Errors/ErrorPage')
            ->where('status', 404)
            ->where('primaryCta.label', 'Back to Studio')
            ->where('primaryCta.href', route('generations.index'))
            ->where('secondaryCta.label', 'View Credits')
            ->where('secondaryCta.href', route('credits.index')));
    }

    public function test_non_admin_403_renders_custom_inertia_error_page_with_studio_cta(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)->get('/admin/reports');

        $response->assertStatus(403);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Errors/ErrorPage')
            ->where('status', 403)
            ->where('title', 'Access Denied')
            ->where('description', 'Operator/admin access is required to view this report.')
            ->where('primaryCta.label', 'Back to Studio')
            ->where('primaryCta.href', route('generations.index'))
            ->where('canRetry', false));
    }

    public function test_500_when_debug_disabled_renders_custom_inertia_error_page_with_retry_and_cta(): void
    {
        config()->set('app.debug', false);

        Route::middleware('web')->get('/_test/trigger-500', function (): never {
            abort(500);
        });

        $response = $this->get('/_test/trigger-500');

        $response->assertStatus(500);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Errors/ErrorPage')
            ->where('status', 500)
            ->where('title', 'Something Went Wrong')
            ->where('canRetry', true)
            ->where('primaryCta.href', route('login')));
    }

    public function test_503_renders_custom_inertia_error_page_with_retry_and_cta(): void
    {
        Route::middleware('web')->get('/_test/trigger-503', function (): never {
            abort(503);
        });

        $response = $this->get('/_test/trigger-503');

        $response->assertStatus(503);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Errors/ErrorPage')
            ->where('status', 503)
            ->where('title', 'Service Unavailable')
            ->where('canRetry', true)
            ->where('primaryCta.href', route('login')));
    }

    public function test_json_requests_still_receive_json_error_responses(): void
    {
        $response = $this->getJson('/this-json-route-does-not-exist');

        $response->assertStatus(404);
        $response->assertJsonStructure(['message']);
    }
}
