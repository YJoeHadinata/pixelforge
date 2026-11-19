<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use App\Queries\AdminUsageReportQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class AdminReportReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.pixelforge.api_key' => 'pf_test_key']);
        config(['services.pixelforge.base_url' => 'https://pixelforge.promptedit.dev/v1']);
    }

    public function test_groups_by_utc_completed_at_date_and_reconciles_against_v1_usage(): void
    {
        $user = User::factory()->create(['name' => 'Alice']);

        // Created on Nov 18 UTC, completed on Nov 19 UTC -> billed on Nov 19 UTC.
        Generation::factory()->for($user)->create([
            'pixelforge_id' => 'gen_utc_1',
            'status' => GenerationStatus::Succeeded,
            'actual_provider_cost_picodollars' => 305_510_000_000, // $0.305510000
            'actual_user_cost_picodollars' => 381_887_500_000,
            'profit_picodollars' => 76_377_500_000,
            'reserved_user_cost_picodollars' => 0,
            'pixelforge_created_at' => '2026-11-18T23:59:50Z',
            'pixelforge_completed_at' => '2026-11-19T00:00:10Z',
            'created_at' => '2026-11-18 23:59:50',
        ]);

        Http::fake([
            'https://pixelforge.promptedit.dev/v1/usage*' => Http::response([
                'object' => 'usage',
                'start_date' => '2026-11-18',
                'end_date' => '2026-11-19',
                'data' => [
                    [
                        'date' => '2026-11-18',
                        'generations' => 0,
                        'cost_usd' => '0.000000000',
                    ],
                    [
                        'date' => '2026-11-19',
                        'generations' => 1,
                        'cost_usd' => '0.305510000',
                    ],
                ],
                'total' => [
                    'generations' => 1,
                    'cost_usd' => '0.305510000',
                ],
            ], 200),
        ]);

        $report = app(AdminUsageReportQuery::class)->build('2026-11-18', '2026-11-19');

        $this->assertTrue($report['summary']['is_fully_reconciled']);
        $this->assertSame('0.000000000', $report['summary']['reconciliation_diff_usd']);
        $this->assertSame('0.305510000', $report['summary']['provider_cost_usd']);
        $this->assertSame('$0.3818875', $report['summary']['credit_spend_usd']);
        $this->assertSame('$0.0763775', $report['summary']['profit_usd']);

        $byDate = collect($report['daily'])->keyBy('date');
        $this->assertSame(0, $byDate['2026-11-18']['local_generations']);
        $this->assertSame(1, $byDate['2026-11-19']['local_generations']);
        $this->assertTrue($byDate['2026-11-19']['is_reconciled']);
    }

    public function test_admin_reports_page_renders_for_admin_and_forbids_regular_users(): void
    {
        Http::fake([
            'https://pixelforge.promptedit.dev/v1/usage*' => Http::response([
                'object' => 'usage',
                'data' => [],
                'total' => ['generations' => 0, 'cost_usd' => '0.000000000'],
            ], 200),
        ]);

        $regularUser = User::factory()->create(['is_admin' => false]);
        $this->actingAs($regularUser)
            ->get(route('admin.reports'))
            ->assertForbidden();

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)
            ->get(route('admin.reports'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Reports')->has('report'));
    }
}
