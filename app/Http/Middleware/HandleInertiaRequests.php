<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Pixelforge\MoneyFormatter;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => function () use ($request): ?array {
                    $user = $request->user();
                    if ($user === null) {
                        return null;
                    }

                    $available = $user->availableBalancePicodollars();

                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'is_admin' => (bool) $user->is_admin,
                        'credit_balance_picodollars' => (int) $user->credit_balance_picodollars,
                        'reserved_balance_picodollars' => (int) $user->reserved_balance_picodollars,
                        'available_balance_picodollars' => $available,
                        'credit_balance_usd' => MoneyFormatter::toExactDisplayUsd((int) $user->credit_balance_picodollars),
                        'reserved_balance_usd' => MoneyFormatter::toExactDisplayUsd((int) $user->reserved_balance_picodollars),
                        'available_balance_usd' => MoneyFormatter::toExactDisplayUsd($available),
                    ];
                },
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
