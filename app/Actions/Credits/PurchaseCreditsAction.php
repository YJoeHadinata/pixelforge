<?php

declare(strict_types=1);

namespace App\Actions\Credits;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\User;
use App\Services\Pixelforge\MoneyFormatter;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class PurchaseCreditsAction
{
    public function __construct(
        private readonly RecordCreditTransaction $ledger,
    ) {}

    public function execute(
        User $user,
        string $amountUsd,
        string $description = 'Simulated credit top-up',
    ): CreditTransaction {
        $amountPicodollars = MoneyFormatter::usdToPicodollars($amountUsd);

        if ($amountPicodollars <= 0) {
            throw new InvalidArgumentException('Top-up amount must be greater than zero.');
        }

        return DB::transaction(function () use ($user, $amountPicodollars, $description): CreditTransaction {
            /** @var User $lockedUser */
            $lockedUser = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            $lockedUser->credit_balance_picodollars = (int) $lockedUser->credit_balance_picodollars + $amountPicodollars;
            $lockedUser->save();

            return $this->ledger->record(
                user: $lockedUser,
                type: CreditTransactionType::TopUp,
                amountPicodollars: $amountPicodollars,
                description: $description,
            );
        });
    }

    public function drainToZero(User $user): ?CreditTransaction
    {
        return DB::transaction(function () use ($user): ?CreditTransaction {
            /** @var User $lockedUser */
            $lockedUser = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            $available = $lockedUser->availableBalancePicodollars();
            if ($available <= 0) {
                return null;
            }

            $lockedUser->credit_balance_picodollars = (int) $lockedUser->reserved_balance_picodollars;
            $lockedUser->save();

            return $this->ledger->record(
                user: $lockedUser,
                type: CreditTransactionType::Adjustment,
                amountPicodollars: -$available,
                description: 'Reviewer testing: drained available credit balance to $0.00',
            );
        });
    }
}
