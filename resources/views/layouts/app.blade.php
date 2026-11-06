<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $attributes->get('title') ? $attributes->get('title') . ' · ' : '' }}SIES Campus Portal</title>
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body>
    <div class="app-shell">
        <x-sidebar />
        <div class="app-main">
            <x-topbar />
            <main class="app-content">
                @if ($attributes->get('title'))
                    <x-page-header :title="$attributes->get('title')" :subtitle="$attributes->get('subtitle')" />
                @endif
                {{ $slot }}
            </main>
            <footer class="app-footer">
                SIES / {{ config('sidebar.' . auth()->user()->role . '.role_label') }} workspace
            </footer>
        </div>
    </div>
    <x-logout-modal />
</body>
</html>
