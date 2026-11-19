<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\Pixelforge\MoneyFormatter;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuthAndDemoLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_grants_starter_credits_and_logs_user_in(): void
    {
        $response = $this->post('/register', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('generations.index'));
        $this->assertAuthenticated();

        $user = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertSame(MoneyFormatter::usdToPicodollars('2.00'), $user->credit_balance_picodollars);
    }

    public function test_demo_reviewer_login_signs_in_with_starter_credits(): void
    {
        $response = $this->post(route('login.demo'), ['role' => 'admin']);

        $response->assertRedirect(route('generations.index'));
        $this->assertAuthenticated();

        $reviewer = User::where('email', DatabaseSeeder::DEMO_REVIEWER_EMAIL)->firstOrFail();
        $this->assertTrue($reviewer->is_admin);
        $this->assertSame(MoneyFormatter::usdToPicodollars('5.00'), $reviewer->credit_balance_picodollars);
    }

    public function test_demo_user_login_signs_in_as_non_admin_without_admin_report_access(): void
    {
        $response = $this->post(route('login.demo'), ['role' => 'user']);

        $response->assertRedirect(route('generations.index'));
        $this->assertAuthenticated();

        $demoUser = User::where('email', DatabaseSeeder::DEMO_USER_EMAIL)->firstOrFail();
        $this->assertFalse($demoUser->is_admin);
        $this->assertSame(MoneyFormatter::usdToPicodollars('2.00'), $demoUser->credit_balance_picodollars);

        $this->get(route('admin.reports'))->assertForbidden();
    }

    public function test_user_can_buy_credits_and_drain_to_zero(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('credits.store'), ['amount_usd' => '10.00'])
            ->assertRedirect();

        $user->refresh();
        $this->assertSame(MoneyFormatter::usdToPicodollars('10.00'), $user->credit_balance_picodollars);

        $this->actingAs($user)
            ->post(route('credits.drain'))
            ->assertRedirect();

        $user->refresh();
        $this->assertSame(0, $user->availableBalancePicodollars());
    }
}
