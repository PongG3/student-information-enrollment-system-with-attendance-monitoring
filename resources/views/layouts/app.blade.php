@php
    // ?embed=1: the page is shown inside a dialog (resources/js/page-dialog.js) — content only, no menu or top bar.
    $embedded = request()->boolean('embed');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @if ($embedded) class="is-embedded" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php $tabTitle = $attributes->get('tab-title') ?? $attributes->get('title'); @endphp
    <title>{{ $tabTitle ? $tabTitle . ' · ' : '' }}SIES Campus Portal</title>
    @if ($embedded)
        <meta name="dialog-title" content="{{ $attributes->get('title') }}">
        <meta name="dialog-subtitle" content="{{ $attributes->get('subtitle') }}">
        <meta name="dialog-size" content="{{ $attributes->get('dialog-size') }}">
    @else
        <script>try { if (localStorage.getItem('sies.sidebar.pinned') === '1') document.documentElement.classList.add('sidebar-pinned'); } catch (e) {}</script>
        {{-- A link in the menu was just clicked: open this page with the menu still expanded, before it paints. --}}
        <script>try { if (sessionStorage.getItem('sies.sidebar.hold') === '1') { sessionStorage.removeItem('sies.sidebar.hold'); document.documentElement.classList.add('sidebar-hold'); } } catch (e) {}</script>
    @endif
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
@if ($embedded)
    <body data-embedded>
        <main class="embed-content" id="main-content">
            {{ $slot }}
        </main>
        <x-confirm-modal />
        <x-toasts />
        @stack('scripts')
    </body>
@else
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
                            :centered="(bool) $attributes->get('centered', false)" :actions="$actions ?? null" />
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
        <x-page-dialog />
        <x-toasts />
        @stack('scripts')
    </body>
@endif
</html>
