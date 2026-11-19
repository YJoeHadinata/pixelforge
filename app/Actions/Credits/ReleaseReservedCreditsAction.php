<?php

declare(strict_types=1);

namespace App\Actions\Credits;

use App\Enums\CreditTransactionType;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ReleaseReservedCreditsAction
{
    public function __construct(
        private readonly RecordCreditTransaction $ledger,
    ) {}

    public function execute(
        Generation $generation,
        string $reason = 'Released reservation for failed generation',
    ): void {
        DB::transaction(function () use ($generation, $reason): void {
            /** @var Generation $lockedGeneration */
            $lockedGeneration = Generation::whereKey($generation->id)->lockForUpdate()->firstOrFail();

            if ($lockedGeneration->settled_at !== null) {
                return;
            }

            $reserved = (int) $lockedGeneration->reserved_user_cost_picodollars;

            if ($reserved > 0) {
                /** @var User $lockedUser */
                $lockedUser = User::whereKey($lockedGeneration->user_id)->lockForUpdate()->firstOrFail();
                $lockedUser->reserved_balance_picodollars = max(
                    0,
                    (int) $lockedUser->reserved_balance_picodollars - $reserved,
                );
                $lockedUser->save();

                $this->ledger->record(
                    user: $lockedUser,
                    type: CreditTransactionType::Release,
                    amountPicodollars: $reserved,
                    description: $reason,
                    generationId: $lockedGeneration->id,
                );
            }

            $lockedGeneration->reserved_user_cost_picodollars = 0;
            $lockedGeneration->settled_at = now()->utc();
            $lockedGeneration->save();
        });
    }
}
