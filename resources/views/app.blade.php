<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <link rel="icon" type="image/svg+xml" href="/favicon.svg" />

        <title inertia>{{ config('app.name', 'Pixelforge Studio') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="min-h-screen bg-[#0a0a0a] font-sans text-neutral-100 antialiased">
        @inertia
    </body>
</html>
