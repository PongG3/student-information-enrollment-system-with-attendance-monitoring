<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $attributes->get('title') ? $attributes->get('title') . ' · ' : '' }}SIES Campus Portal</title>
    <script>try { if (localStorage.getItem('sies.sidebar.pinned') === '1') document.documentElement.classList.add('sidebar-pinned'); } catch (e) {}</script>
    {{-- A link in the menu was just clicked: open this page with the menu still expanded, before it paints. --}}
    <script>try { if (sessionStorage.getItem('sies.sidebar.hold') === '1') { sessionStorage.removeItem('sies.sidebar.hold'); document.documentElement.classList.add('sidebar-hold'); } } catch (e) {}</script>
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body>
    <a href="#main-content" class="skip-link visually-hidden-focusable">Skip to content</a>
    <div class="app-shell">
        <x-sidebar />
        <div class="app-main">
            <x-topbar />
            <main class="app-content" id="main-content" tabindex="-1">
                @if ($attributes->get('title'))
                    <x-page-header :title="$attributes->get('title')" :subtitle="$attributes->get('subtitle')"
                        :back="$attributes->get('back')" :back-label="$attributes->get('back-label', 'Back')"
                        :centered="(bool) $attributes->get('centered', false)" />
                @endif
                {{ $slot }}
            </main>
            <footer class="app-footer">
                SIES / {{ config('sidebar.' . auth()->user()->role . '.role_label') }} workspace
            </footer>
        </div>
    </div>
    <x-logout-modal />
    <x-confirm-modal />
    <x-session-timeout-modal />
    <x-toasts />
    @stack('scripts')
</body>
</html>
