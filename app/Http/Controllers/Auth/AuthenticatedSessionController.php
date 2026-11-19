<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Credits\PurchaseCreditsAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'demoAdminEmail' => DatabaseSeeder::DEMO_REVIEWER_EMAIL,
            'demoUserEmail' => DatabaseSeeder::DEMO_USER_EMAIL,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('generations.index'));
    }

    public function demoLogin(Request $request, PurchaseCreditsAction $purchaseCredits): RedirectResponse
    {
        $role = $request->validate([
            'role' => ['nullable', 'string', 'in:admin,user'],
        ])['role'] ?? 'admin';

        $isAdmin = $role === 'admin';
        $email = $isAdmin ? DatabaseSeeder::DEMO_REVIEWER_EMAIL : DatabaseSeeder::DEMO_USER_EMAIL;
        $name = $isAdmin ? 'PromptEdit Reviewer (Admin)' : 'Demo User';
        $starterUsd = $isAdmin ? '5.00' : '2.00';

        $account = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_admin' => $isAdmin,
                'credit_balance_picodollars' => 0,
                'reserved_balance_picodollars' => 0,
            ],
        );

        if ((int) $account->credit_balance_picodollars === 0 && ! $account->creditTransactions()->exists()) {
            $purchaseCredits->execute($account, $starterUsd, "Initial {$role} starter credits (\${$starterUsd})");
        }

        Auth::login($account, true);
        $request->session()->regenerate();

        return redirect()->route('generations.index');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
