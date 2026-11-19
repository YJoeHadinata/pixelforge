<?php

declare(strict_types=1);

namespace App\Actions\Credits;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\User;
use App\Services\Pixelforge\Exceptions\InsufficientCreditsException;
use Illuminate\Support\Facades\DB;

final class ReserveCreditsAction
{
    public function __construct(
        private readonly RecordCreditTransaction $ledger,
    ) {}

    public function execute(
        User $user,
        int $amountPicodollars,
        string $generationId,
        string $description = 'Reserved max estimated generation cost',
    ): CreditTransaction {
        return DB::transaction(function () use ($user, $amountPicodollars, $generationId, $description): CreditTransaction {
            /** @var User $lockedUser */
            $lockedUser = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            $available = $lockedUser->availableBalancePicodollars();
            if ($available < $amountPicodollars) {
                throw new InsufficientCreditsException(
                    availablePicodollars: $available,
                    requiredPicodollars: $amountPicodollars,
                );
            }

            $lockedUser->reserved_balance_picodollars = (int) $lockedUser->reserved_balance_picodollars + $amountPicodollars;
            $lockedUser->save();

            return $this->ledger->record(
                user: $lockedUser,
                type: CreditTransactionType::Reserve,
                amountPicodollars: -$amountPicodollars,
                description: $description,
                generationId: $generationId,
            );
        });
    }
}
