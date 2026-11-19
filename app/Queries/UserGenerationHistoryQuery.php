<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\User;
use App\Services\Pixelforge\MoneyFormatter;
use Illuminate\Pagination\LengthAwarePaginator;

final class UserGenerationHistoryQuery
{
    /**
     * @return array<string, mixed>
     */
    public function historyForUser(User $user, int $perPage = 15): array
    {
        /** @var LengthAwarePaginator<int, Generation> $paginator */
        $paginator = $user->generations()
            ->latest('created_at')
            ->paginate($perPage)
            ->withQueryString();

        $items = $paginator->getCollection()
            ->map(fn (Generation $generation): array => $this->serializeGeneration($generation))
            ->values()
            ->all();

        $hasInFlight = $user->generations()
            ->whereIn('status', ['submitting', 'queued_locally', 'queued', 'processing'])
            ->exists();

        return [
            'items' => $items,
            'has_in_flight' => $hasInFlight,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function statusSnapshotForUser(User $user, int $limit = 30): array
    {
        $generations = $user->generations()
            ->latest('created_at')
            ->limit($limit)
            ->get();

        return [
            'items' => $generations->map(fn (Generation $g): array => $this->serializeGeneration($g))->values()->all(),
            'has_in_flight' => $generations->contains(fn (Generation $g): bool => $g->status->isInFlight()),
            'balance' => [
                'credit_balance_picodollars' => (int) $user->credit_balance_picodollars,
                'reserved_balance_picodollars' => (int) $user->reserved_balance_picodollars,
                'available_balance_picodollars' => $user->availableBalancePicodollars(),
                'credit_balance_usd' => MoneyFormatter::toExactDisplayUsd((int) $user->credit_balance_picodollars),
                'reserved_balance_usd' => MoneyFormatter::toExactDisplayUsd((int) $user->reserved_balance_picodollars),
                'available_balance_usd' => MoneyFormatter::toExactDisplayUsd($user->availableBalancePicodollars()),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentTransactionsForUser(User $user, int $limit = 50): array
    {
        return $user->creditTransactions()
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(static fn (CreditTransaction $tx): array => [
                'id' => $tx->id,
                'generation_id' => $tx->generation_id,
                'type' => $tx->type->value,
                'amount_picodollars' => (int) $tx->amount_picodollars,
                'amount_usd' => MoneyFormatter::toExactDisplayUsd((int) $tx->amount_picodollars),
                'balance_after_usd' => MoneyFormatter::toExactDisplayUsd((int) $tx->balance_after_picodollars),
                'reserved_after_usd' => MoneyFormatter::toExactDisplayUsd((int) $tx->reserved_after_picodollars),
                'description' => $tx->description,
                'created_at' => $tx->created_at?->utc()->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeGeneration(Generation $generation): array
    {
        return [
            'id' => $generation->id,
            'pixelforge_id' => $generation->pixelforge_id,
            'status' => $generation->status->value,
            'is_in_flight' => $generation->status->isInFlight(),
            'prompt' => $generation->prompt,
            'size' => $generation->size->value,
            'quality' => $generation->quality->value,
            'reference_images' => $generation->reference_images ?? [],
            'usage' => $generation->usage,
            'rates_snapshot' => $generation->rates_snapshot,
            'estimated_min_user_cost_usd' => MoneyFormatter::toExactDisplayUsd((int) $generation->estimated_min_user_cost_picodollars),
            'estimated_expected_user_cost_usd' => MoneyFormatter::toExactDisplayUsd((int) $generation->estimated_expected_user_cost_picodollars),
            'estimated_max_user_cost_usd' => MoneyFormatter::toExactDisplayUsd((int) $generation->estimated_max_user_cost_picodollars),
            'actual_provider_cost_usd' => $generation->actual_provider_cost_picodollars !== null
                ? '$'.MoneyFormatter::toNanodollarString((int) $generation->actual_provider_cost_picodollars)
                : null,
            'actual_user_cost_usd' => $generation->actual_user_cost_picodollars !== null
                ? MoneyFormatter::toExactDisplayUsd((int) $generation->actual_user_cost_picodollars)
                : null,
            'actual_user_cost_picodollars' => $generation->actual_user_cost_picodollars,
            'image_url' => $generation->image_url,
            'image_url_expires_at' => $generation->image_url_expires_at?->utc()->toIso8601String(),
            'is_image_expired' => $generation->isImageExpired(),
            'error_code' => $generation->error_code,
            'error_message' => $generation->error_message,
            'created_at' => $generation->created_at?->utc()->toIso8601String(),
            'pixelforge_started_at' => $generation->pixelforge_started_at?->utc()->toIso8601String(),
            'pixelforge_completed_at' => $generation->pixelforge_completed_at?->utc()->toIso8601String(),
        ];
    }
}
