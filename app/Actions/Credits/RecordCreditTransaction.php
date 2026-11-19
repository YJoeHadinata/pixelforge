<?php

declare(strict_types=1);

namespace App\Actions\Credits;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\User;

final class RecordCreditTransaction
{
    public function record(
        User $user,
        CreditTransactionType $type,
        int $amountPicodollars,
        string $description,
        ?string $generationId = null,
    ): CreditTransaction {
        return CreditTransaction::create([
            'user_id' => $user->id,
            'generation_id' => $generationId,
            'type' => $type,
            'amount_picodollars' => $amountPicodollars,
            'balance_after_picodollars' => (int) $user->credit_balance_picodollars,
            'reserved_after_picodollars' => (int) $user->reserved_balance_picodollars,
            'description' => $description,
            'created_at' => now()->utc(),
        ]);
    }
}
