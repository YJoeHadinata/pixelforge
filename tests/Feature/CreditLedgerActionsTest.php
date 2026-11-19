<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Credits\PurchaseCreditsAction;
use App\Actions\Credits\ReleaseReservedCreditsAction;
use App\Actions\Credits\ReserveCreditsAction;
use App\Actions\Credits\SettleGenerationCreditsAction;
use App\Enums\CreditTransactionType;
use App\Models\Generation;
use App\Models\User;
use App\Services\Pixelforge\Exceptions\InsufficientCreditsException;
use App\Services\Pixelforge\MoneyFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CreditLedgerActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_top_up_reserve_and_idempotent_settlement(): void
    {
        $user = User::factory()->create();
        $purchase = app(PurchaseCreditsAction::class);
        $reserve = app(ReserveCreditsAction::class);
        $settle = app(SettleGenerationCreditsAction::class);

        $purchase->execute($user, '5.00');
        $user->refresh();
        $this->assertSame(MoneyFormatter::usdToPicodollars('5.00'), $user->credit_balance_picodollars);

        $maxReserve = 100_000_000_000; // $0.10
        $generation = Generation::factory()->for($user)->create([
            'reserved_user_cost_picodollars' => $maxReserve,
        ]);

        $reserve->execute($user, $maxReserve, $generation->id);
        $user->refresh();
        $this->assertSame($maxReserve, $user->reserved_balance_picodollars);
        $this->assertSame(4_900_000_000_000, $user->availableBalancePicodollars());

        $actualCharge = 60_000_000_000; // $0.06
        $settle->execute($generation, $actualCharge);
        // Second call must be a complete no-op
        $settle->execute($generation, $actualCharge);

        $user->refresh();
        $generation->refresh();

        $this->assertSame(0, $user->reserved_balance_picodollars);
        $this->assertSame(4_940_000_000_000, $user->credit_balance_picodollars);
        $this->assertSame(0, $generation->reserved_user_cost_picodollars);
        $this->assertNotNull($generation->settled_at);

        $this->assertSame(1, $user->creditTransactions()->where('type', CreditTransactionType::Charge)->count());
    }

    public function test_release_frees_full_reservation_with_zero_charge_on_failure(): void
    {
        $user = User::factory()->create([
            'credit_balance_picodollars' => 1_000_000_000_000,
        ]);
        $reserve = app(ReserveCreditsAction::class);
        $release = app(ReleaseReservedCreditsAction::class);

        $generation = Generation::factory()->for($user)->create([
            'reserved_user_cost_picodollars' => 200_000_000_000,
        ]);

        $reserve->execute($user, 200_000_000_000, $generation->id);
        $release->execute($generation);
        $release->execute($generation); // idempotent

        $user->refresh();
        $this->assertSame(1_000_000_000_000, $user->credit_balance_picodollars);
        $this->assertSame(0, $user->reserved_balance_picodollars);
        $this->assertSame(1_000_000_000_000, $user->availableBalancePicodollars());
        $this->assertSame(0, $user->creditTransactions()->where('type', CreditTransactionType::Charge)->count());
    }

    public function test_blocks_reservation_when_available_balance_is_insufficient(): void
    {
        $user = User::factory()->create([
            'credit_balance_picodollars' => 50_000_000_000,
        ]);
        $generation = Generation::factory()->for($user)->create();

        $this->expectException(InsufficientCreditsException::class);

        app(ReserveCreditsAction::class)->execute($user, 100_000_000_000, $generation->id);
    }

    public function test_drain_to_zero_leaves_zero_available_without_touching_active_reserves(): void
    {
        $user = User::factory()->create([
            'credit_balance_picodollars' => 1_000_000_000_000,
            'reserved_balance_picodollars' => 250_000_000_000,
        ]);

        app(PurchaseCreditsAction::class)->drainToZero($user);
        $user->refresh();

        $this->assertSame(250_000_000_000, $user->credit_balance_picodollars);
        $this->assertSame(250_000_000_000, $user->reserved_balance_picodollars);
        $this->assertSame(0, $user->availableBalancePicodollars());
    }
}
