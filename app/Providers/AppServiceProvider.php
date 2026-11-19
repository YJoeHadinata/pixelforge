<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('auth', static fn (Request $request): Limit => Limit::perMinute(10)->by($request->ip() ?? 'unknown'));

        RateLimiter::for('generations', static fn (Request $request): Limit => Limit::perMinute(15)->by((string) ($request->user()?->id ?? $request->ip())));

        RateLimiter::for('generation-sync', static fn (Request $request): Limit => Limit::perMinute(45)->by((string) ($request->user()?->id ?? $request->ip())));

        RateLimiter::for('credits', static fn (Request $request): Limit => Limit::perMinute(20)->by((string) ($request->user()?->id ?? $request->ip())));

        RateLimiter::for('webhooks', static fn (Request $request): Limit => Limit::perMinute(120)->by($request->ip() ?? 'unknown'));
    }
}
