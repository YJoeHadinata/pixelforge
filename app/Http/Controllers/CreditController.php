<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Credits\PurchaseCreditsAction;
use App\Http\Requests\PurchaseCreditsRequest;
use App\Models\User;
use App\Queries\UserGenerationHistoryQuery;
use App\Services\Pixelforge\MoneyFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CreditController extends Controller
{
    public function index(Request $request, UserGenerationHistoryQuery $historyQuery): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Credits/Index', [
            'transactions' => $historyQuery->recentTransactionsForUser($user),
        ]);
    }

    public function store(PurchaseCreditsRequest $request, PurchaseCreditsAction $purchase): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $amount = bcadd((string) $request->validated('amount_usd'), '0', 2);

        $tx = $purchase->execute($user, $amount);
        $display = MoneyFormatter::toExactDisplayUsd((int) $tx->amount_picodollars);

        return back()->with('success', "Added {$display} simulated credits to your balance.");
    }

    public function drain(Request $request, PurchaseCreditsAction $purchase): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $purchase->drainToZero($user);

        return back()->with('success', 'Available credit balance set to $0.00 for testing insufficient-balance checks.');
    }
}
