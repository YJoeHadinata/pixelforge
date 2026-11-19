<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Credits\PurchaseCreditsAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

final class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(Request $request, PurchaseCreditsAction $purchaseCredits): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        /** @var User $user */
        $user = DB::transaction(function () use ($validated, $purchaseCredits): User {
            $created = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'is_admin' => false,
                'credit_balance_picodollars' => 0,
                'reserved_balance_picodollars' => 0,
            ]);

            $purchaseCredits->execute($created, '2.00', 'Welcome starter credits ($2.00)');

            return $created;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('generations.index');
    }
}
