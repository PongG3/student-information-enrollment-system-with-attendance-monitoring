@php
    $user = auth()->user();
    $canSearch = in_array($user->role, ['admin', 'registrar', 'teacher'], true);
@endphp
<header class="topbar">
    <button type="button" class="topbar-menu" id="sidebarToggle" aria-label="Open the menu" aria-controls="sidebar">
        <x-icon name="menu" :size="22" />
    </button>

    @if ($canSearch)
        <form method="GET" action="{{ route('search') }}" role="search" class="topbar-search-form">
            <x-icon name="search" :size="16" />
            <input type="search" name="q" value="{{ request()->routeIs('search') ? request('q') : '' }}"
                   class="topbar-search" maxlength="60" autocomplete="off"
                   placeholder="{{ $user->role === 'teacher' ? 'Search my classes...' : 'Search students or classes...' }}"
                   aria-label="Search">
        </form>
    @else
        <div></div>
    @endif

    <div class="dropdown">
        <button type="button" class="account-button" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="text-end lh-sm">
                <span class="d-block fw-semibold">{{ $user->name }}</span>
                <small class="text-secondary">{{ config('sidebar.' . $user->role . '.role_label') }}</small>
            </span>
            <x-icon name="chevron" :size="16" />
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
            <li><a class="dropdown-item" href="{{ route('account.password') }}">Change Password</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#logoutModal">Sign out</button></li>
        </ul>
    </div>
</header>
