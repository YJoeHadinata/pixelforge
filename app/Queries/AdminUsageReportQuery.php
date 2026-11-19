<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use App\Services\Pixelforge\MoneyFormatter;
use App\Services\Pixelforge\PixelforgeClient;
use Carbon\CarbonImmutable;
use Throwable;

final class AdminUsageReportQuery
{
    public function __construct(
        private readonly PixelforgeClient $client,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(?string $startDate = null, ?string $endDate = null): array
    {
        $endUtc = $endDate !== null && $endDate !== ''
            ? CarbonImmutable::parse($endDate, 'UTC')->startOfDay()
            : CarbonImmutable::now('UTC')->startOfDay();

        $startUtc = $startDate !== null && $startDate !== ''
            ? CarbonImmutable::parse($startDate, 'UTC')->startOfDay()
            : $endUtc->subDays(13);

        if ($startUtc->greaterThan($endUtc)) {
            [$startUtc, $endUtc] = [$endUtc, $startUtc];
        }

        if ($startUtc->diffInDays($endUtc) > 91) {
            $startUtc = $endUtc->subDays(91);
        }

        $startStr = $startUtc->format('Y-m-d');
        $endStr = $endUtc->format('Y-m-d');

        $succeeded = Generation::query()
            ->with('user:id,name,email')
            ->where('status', GenerationStatus::Succeeded)
            ->whereNotNull('pixelforge_completed_at')
            ->whereBetween('pixelforge_completed_at', [$startUtc->startOfDay(), $endUtc->endOfDay()])
            ->get();

        $failedGenerations = Generation::query()
            ->where('status', GenerationStatus::Failed)
            ->whereBetween('created_at', [$startUtc->startOfDay(), $endUtc->endOfDay()])
            ->get(['id', 'user_id']);

        $failedCount = $failedGenerations->count();

        $remoteUsage = $this->fetchRemoteUsage($startStr, $endStr);
        $remoteByDate = [];
        foreach ($remoteUsage['data'] ?? [] as $row) {
            if (is_array($row) && isset($row['date'])) {
                $remoteByDate[(string) $row['date']] = $row;
            }
        }

        $dailyBuckets = $this->initializeDailyBuckets($startUtc, $endUtc);
        $userBuckets = [];

        foreach (User::query()->orderBy('id')->get() as $user) {
            $userBuckets[(int) $user->id] = [
                'user_id' => (int) $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'available_balance_usd' => MoneyFormatter::toExactDisplayUsd($user->availableBalancePicodollars()),
                'succeeded_count' => 0,
                'failed_count' => 0,
                'provider_pico' => 0,
                'user_pico' => 0,
                'profit_pico' => 0,
            ];
        }

        foreach ($failedGenerations as $failedGen) {
            $uid = (int) $failedGen->user_id;
            if (isset($userBuckets[$uid])) {
                $userBuckets[$uid]['failed_count']++;
            }
        }

        $totalProviderPico = 0;
        $totalUserPico = 0;
        $totalProfitPico = 0;

        foreach ($succeeded as $generation) {
            $providerPico = (int) ($generation->actual_provider_cost_picodollars ?? 0);
            $userPico = (int) ($generation->actual_user_cost_picodollars ?? 0);
            $profitPico = (int) ($generation->profit_picodollars ?? ($userPico - $providerPico));

            $totalProviderPico += $providerPico;
            $totalUserPico += $userPico;
            $totalProfitPico += $profitPico;

            $utcDate = $generation->pixelforge_completed_at?->utc()->format('Y-m-d') ?? $endStr;
            if (isset($dailyBuckets[$utcDate])) {
                $dailyBuckets[$utcDate]['generations']++;
                $dailyBuckets[$utcDate]['provider_pico'] += $providerPico;
                $dailyBuckets[$utcDate]['user_pico'] += $userPico;
                $dailyBuckets[$utcDate]['profit_pico'] += $profitPico;
            }

            $userId = (int) $generation->user_id;
            if (! isset($userBuckets[$userId])) {
                $userBuckets[$userId] = [
                    'user_id' => $userId,
                    'name' => $generation->user?->name ?? "User #{$userId}",
                    'email' => $generation->user?->email ?? '',
                    'available_balance_usd' => '$0.00',
                    'succeeded_count' => 0,
                    'failed_count' => 0,
                    'provider_pico' => 0,
                    'user_pico' => 0,
                    'profit_pico' => 0,
                ];
            }

            $userBuckets[$userId]['succeeded_count']++;
            $userBuckets[$userId]['provider_pico'] += $providerPico;
            $userBuckets[$userId]['user_pico'] += $userPico;
            $userBuckets[$userId]['profit_pico'] += $profitPico;
        }

        $dailyRows = [];
        foreach (array_reverse($dailyBuckets, true) as $date => $bucket) {
            $remoteRow = $remoteByDate[$date] ?? null;
            $remoteCostStr = $remoteRow !== null ? (string) ($remoteRow['cost_usd'] ?? '0.000000000') : null;
            $remoteProviderPico = $remoteCostStr !== null ? MoneyFormatter::usdToPicodollars($remoteCostStr) : null;
            $diffPico = $remoteProviderPico !== null ? ($bucket['provider_pico'] - $remoteProviderPico) : null;

            $dailyRows[] = [
                'date' => $date,
                'local_generations' => $bucket['generations'],
                'remote_generations' => $remoteRow !== null ? (int) ($remoteRow['generations'] ?? 0) : null,
                'local_provider_cost_usd' => MoneyFormatter::toNanodollarString($bucket['provider_pico']),
                'remote_provider_cost_usd' => $remoteCostStr,
                'diff_usd' => $diffPico !== null ? MoneyFormatter::toNanodollarString($diffPico) : null,
                'is_reconciled' => $diffPico === 0 && ($remoteRow === null || $bucket['generations'] === (int) ($remoteRow['generations'] ?? 0)),
                'credit_spend_usd' => MoneyFormatter::toExactDisplayUsd($bucket['user_pico']),
                'profit_usd' => MoneyFormatter::toExactDisplayUsd($bucket['profit_pico']),
            ];
        }

        $userRows = array_values(array_map(static fn (array $row): array => [
            'user_id' => $row['user_id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'available_balance_usd' => $row['available_balance_usd'],
            'succeeded_count' => $row['succeeded_count'],
            'failed_count' => $row['failed_count'],
            'credit_spend_usd' => MoneyFormatter::toExactDisplayUsd($row['user_pico']),
            'provider_cost_usd' => '$'.MoneyFormatter::toNanodollarString($row['provider_pico']),
            'profit_usd' => MoneyFormatter::toExactDisplayUsd($row['profit_pico']),
        ], $userBuckets));

        usort($userRows, static fn (array $a, array $b): int => [$b['succeeded_count'], $b['failed_count'], $a['user_id']]
            <=> [$a['succeeded_count'], $a['failed_count'], $b['user_id']]);

        $remoteTotalCostStr = isset($remoteUsage['total']['cost_usd']) ? (string) $remoteUsage['total']['cost_usd'] : null;
        $remoteTotalPico = $remoteTotalCostStr !== null ? MoneyFormatter::usdToPicodollars($remoteTotalCostStr) : null;
        $totalDiffPico = $remoteTotalPico !== null ? ($totalProviderPico - $remoteTotalPico) : null;

        return [
            'filters' => [
                'start_date' => $startStr,
                'end_date' => $endStr,
            ],
            'summary' => [
                'succeeded_generations' => $succeeded->count(),
                'failed_generations' => $failedCount,
                'credit_spend_usd' => MoneyFormatter::toExactDisplayUsd($totalUserPico),
                'provider_cost_usd' => MoneyFormatter::toNanodollarString($totalProviderPico),
                'profit_usd' => MoneyFormatter::toExactDisplayUsd($totalProfitPico),
                'remote_provider_cost_usd' => $remoteTotalCostStr,
                'remote_generations' => isset($remoteUsage['total']['generations']) ? (int) $remoteUsage['total']['generations'] : null,
                'reconciliation_diff_usd' => $totalDiffPico !== null ? MoneyFormatter::toNanodollarString($totalDiffPico) : null,
                'is_fully_reconciled' => $totalDiffPico === 0,
                'remote_error' => $remoteUsage['_error'] ?? null,
            ],
            'daily' => $dailyRows,
            'by_user' => $userRows,
        ];
    }

    /**
     * @return array<string, array{generations: int, provider_pico: int, user_pico: int, profit_pico: int}>
     */
    private function initializeDailyBuckets(CarbonImmutable $startUtc, CarbonImmutable $endUtc): array
    {
        $buckets = [];
        $cursor = $startUtc;

        while ($cursor->lessThanOrEqualTo($endUtc)) {
            $buckets[$cursor->format('Y-m-d')] = [
                'generations' => 0,
                'provider_pico' => 0,
                'user_pico' => 0,
                'profit_pico' => 0,
            ];
            $cursor = $cursor->addDay();
        }

        return $buckets;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchRemoteUsage(string $startDate, string $endDate): array
    {
        try {
            return $this->client->getUsage($startDate, $endDate);
        } catch (Throwable $e) {
            return ['_error' => $e->getMessage()];
        }
    }
}
