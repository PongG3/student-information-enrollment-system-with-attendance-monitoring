@php
    $role = auth()->user()->role;
    $workspace = config('sidebar.' . $role);
    $badges = \App\Services\MenuBadges::for(auth()->user());
    $lastGroup = null;
@endphp
{{-- A slim icon strip that opens over the page when the pointer or keyboard focus is on it. --}}
<aside class="sidebar" id="sidebar" aria-label="Main menu">
    <div class="sidebar-panel">
        <div class="sidebar-top">
            <x-brand />
            <button type="button" class="sidebar-pin" id="sidebarPin" aria-pressed="false" aria-label="Keep the menu open" title="Keep the menu open">
                <x-icon name="pin" :size="16" />
            </button>
        </div>
        <nav class="sidebar-nav" aria-label="{{ $workspace['workspace'] }}">
            @foreach ($workspace['items'] as $item)
                @if (($item['group'] ?? null) !== $lastGroup)
                    @php $lastGroup = $item['group'] ?? null; @endphp
                    @if ($lastGroup)
                        <div class="sidebar-group">{{ $lastGroup }}</div>
                    @endif
                @endif
                @php
                    $active = request()->routeIs(...(array) $item['active']);
                    $count = isset($item['badge']) ? ($badges[$item['badge']] ?? 0) : 0;
                @endphp
                <a href="{{ route($item['route']) }}" class="nav-link {{ $active ? 'active' : '' }}"
                   title="{{ $item['label'] }}" @if ($active) aria-current="page" @endif>
                    <x-icon :name="$item['icon'] ?? 'dashboard'" />
                    <span class="nav-text">{{ $item['label'] }}</span>
                    @if ($count > 0)
                        <span class="nav-badge" title="{{ $count }} waiting"><span class="visually-hidden">{{ $count }} waiting</span><span aria-hidden="true">{{ $count > 99 ? '99+' : $count }}</span></span>
                    @endif
                </a>
            @endforeach
        </nav>
        <div class="sidebar-foot">
            <button type="button" class="nav-link" title="Logout" data-bs-toggle="modal" data-bs-target="#logoutModal">
                <x-icon name="logout" />
                <span class="nav-text">Logout</span>
            </button>
        </div>
    </div>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
