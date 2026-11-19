<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Credits\PurchaseCreditsAction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class DatabaseSeeder extends Seeder
{
    public const DEMO_REVIEWER_EMAIL = 'reviewer@promptedit.test';

    public const DEMO_USER_EMAIL = 'user@promptedit.test';

    public function run(): void
    {
        $purchase = app(PurchaseCreditsAction::class);

        $reviewer = User::query()->firstOrCreate(
            ['email' => self::DEMO_REVIEWER_EMAIL],
            [
                'name' => 'PromptEdit Reviewer (Admin)',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_admin' => true,
                'credit_balance_picodollars' => 0,
                'reserved_balance_picodollars' => 0,
            ],
        );

        if ((int) $reviewer->credit_balance_picodollars === 0 && ! $reviewer->creditTransactions()->exists()) {
            $purchase->execute($reviewer, '5.00', 'Initial admin starter credits ($5.00)');
        }

        $demoUser = User::query()->firstOrCreate(
            ['email' => self::DEMO_USER_EMAIL],
            [
                'name' => 'Demo User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_admin' => false,
                'credit_balance_picodollars' => 0,
                'reserved_balance_picodollars' => 0,
            ],
        );

        if ((int) $demoUser->credit_balance_picodollars === 0 && ! $demoUser->creditTransactions()->exists()) {
            $purchase->execute($demoUser, '2.00', 'Initial demo user starter credits ($2.00)');
        }
    }
}
