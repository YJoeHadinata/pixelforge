<?php

declare(strict_types=1);

namespace App\Actions\Credits;

use App\Enums\CreditTransactionType;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SettleGenerationCreditsAction
{
    public function __construct(
        private readonly RecordCreditTransaction $ledger,
    ) {}

    public function execute(Generation $generation, int $actualUserCostPicodollars): void
    {
        DB::transaction(function () use ($generation, $actualUserCostPicodollars): void {
            /** @var Generation $lockedGeneration */
            $lockedGeneration = Generation::whereKey($generation->id)->lockForUpdate()->firstOrFail();

            if ($lockedGeneration->settled_at !== null) {
                return;
            }

            /** @var User $lockedUser */
            $lockedUser = User::whereKey($lockedGeneration->user_id)->lockForUpdate()->firstOrFail();

            $reserved = (int) $lockedGeneration->reserved_user_cost_picodollars;
            $lockedUser->reserved_balance_picodollars = max(
                0,
                (int) $lockedUser->reserved_balance_picodollars - $reserved,
            );
            $lockedUser->credit_balance_picodollars = max(
                0,
                (int) $lockedUser->credit_balance_picodollars - $actualUserCostPicodollars,
            );
            $lockedUser->save();

            $lockedGeneration->reserved_user_cost_picodollars = 0;
            $lockedGeneration->settled_at = now()->utc();
            $lockedGeneration->save();

            if ($reserved > 0) {
                $this->ledger->record(
                    user: $lockedUser,
                    type: CreditTransactionType::Release,
                    amountPicodollars: $reserved,
                    description: 'Released credit reservation upon generation completion',
                    generationId: $lockedGeneration->id,
                );
            }

            if ($actualUserCostPicodollars > 0) {
                $this->ledger->record(
                    user: $lockedUser,
                    type: CreditTransactionType::Charge,
                    amountPicodollars: -$actualUserCostPicodollars,
                    description: 'Charged actual generation cost (+25% markup)',
                    generationId: $lockedGeneration->id,
                );
            }
        });
    }
}
