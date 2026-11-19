<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
        <title>@yield('code') — @yield('title') · {{ config('app.name', 'Pixelforge Studio') }}</title>
        <style>
            * { box-sizing: border-box; }
            body {
                margin: 0;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 3rem 1rem;
                background: #0a0a0a;
                color: #f5f5f5;
                font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                -webkit-font-smoothing: antialiased;
            }
            .card {
                width: 100%;
                max-width: 32rem;
                background: #121212;
                border: 1px solid #262626;
                border-radius: 1rem;
                padding: 2rem;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.65);
            }
            .header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
            }
            .brand {
                display: inline-flex;
                align-items: center;
                gap: 0.625rem;
                color: #ffffff;
                text-decoration: none;
                font-weight: 600;
                font-size: 0.875rem;
            }
            .logo {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 2rem;
                height: 2rem;
                border-radius: 0.5rem;
                background: #ffffff;
                color: #0a0a0a;
                font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
                font-size: 0.75rem;
                font-weight: 800;
            }
            .pill {
                display: inline-flex;
                align-items: center;
                padding: 0.125rem 0.625rem;
                border-radius: 9999px;
                border: 1px solid #404040;
                background: #171717;
                color: #d4d4d4;
                font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
                font-size: 0.75rem;
                font-weight: 600;
            }
            .code {
                margin: 2rem 0 0;
                font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
                font-size: 3rem;
                font-weight: 700;
                line-height: 1;
                color: #ffffff;
            }
            h1 {
                margin: 0.75rem 0 0;
                font-size: 1.25rem;
                font-weight: 600;
                color: #ffffff;
            }
            p.desc {
                margin: 0.5rem 0 0;
                font-size: 0.875rem;
                line-height: 1.6;
                color: #a3a3a3;
            }
            .actions {
                margin-top: 2rem;
                padding-top: 1.5rem;
                border-top: 1px solid #262626;
                display: flex;
                flex-wrap: wrap;
                gap: 0.75rem;
            }
            .btn-primary {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 0.5rem 1rem;
                border-radius: 0.5rem;
                background: #ffffff;
                color: #0a0a0a;
                font-size: 0.875rem;
                font-weight: 500;
                text-decoration: none;
            }
            .btn-primary:hover { background: #e5e5e5; }
            .btn-secondary {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 0.5rem 1rem;
                border-radius: 0.5rem;
                border: 1px solid #404040;
                background: #171717;
                color: #f5f5f5;
                font-size: 0.875rem;
                font-weight: 500;
                text-decoration: none;
                cursor: pointer;
            }
            .btn-secondary:hover { background: #262626; }
        </style>
    </head>
    <body>
        <main class="card">
            <div class="header">
                <a href="{{ url('/') }}" class="brand">
                    <span class="logo">PF</span>
                    <span>Pixelforge Studio</span>
                </a>
                <span class="pill">HTTP @yield('code')</span>
            </div>

            <p class="code">@yield('code')</p>
            <h1>@yield('title')</h1>
            <p class="desc">@yield('message')</p>

            <div class="actions">
                <a href="{{ url('/') }}" class="btn-primary">Back to Home</a>
                <button type="button" class="btn-secondary" onclick="window.location.reload()">Try Again</button>
            </div>
        </main>
    </body>
</html>
