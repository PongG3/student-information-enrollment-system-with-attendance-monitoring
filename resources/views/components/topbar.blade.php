@php
    $user = auth()->user();
    $canSearch = in_array($user->role, ['admin', 'registrar', 'teacher'], true);
    $roleLabel = config('sidebar.' . $user->role . '.role_label');
    $initials = collect(preg_split('/\s+/', trim($user->name)))->filter()->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    $notifications = \App\Support\Navigation::notifications($user);
    $waiting = collect($notifications)->sum('count');
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
    @endif

    <div class="topbar-actions">
        {{-- Notifications: the same "needs attention" items as the dashboard, from any page. --}}
        @if ($canSearch)
            <div class="dropdown">
                <button type="button" class="topbar-icon-button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"
                        aria-label="{{ $waiting > 0 ? "Notifications: {$waiting} waiting" : 'Notifications: nothing waiting' }}" title="Notifications">
                    <x-icon name="bell" :size="20" />
                    @if ($waiting > 0)
                        <span class="topbar-dot" aria-hidden="true">{{ $waiting > 99 ? '99+' : $waiting }}</span>
                    @endif
                </button>
                <div class="dropdown-menu dropdown-menu-end notify-menu">
                    <div class="notify-head">
                        <h2>Notifications</h2>
                        @if ($notifications)
                            <span class="small text-secondary">{{ count($notifications) }} {{ \Illuminate\Support\Str::plural('item', count($notifications)) }}</span>
                        @endif
                    </div>
                    @forelse ($notifications as $item)
                        <a href="{{ $item['url'] }}" class="notify-item">
                            <span class="attention-count is-{{ $item['tone'] }}">{{ $item['count'] }}</span>
                            <span class="notify-text">{{ $item['label'] }}</span>
                        </a>
                    @empty
                        <div class="notify-empty">
                            <div class="empty-state-icon" aria-hidden="true"><x-icon name="check" :size="20" /></div>
                            You're all caught up.
                        </div>
                    @endforelse
                </div>
            </div>
        @endif

        <div class="dropdown">
            <button type="button" class="account-button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu for {{ $user->name }}">
                <span class="avatar" aria-hidden="true">{{ $initials }}</span>
                <span class="account-text">
                    <span class="account-name">{{ $user->name }}</span>
                    <span class="account-role">{{ $roleLabel }}</span>
                </span>
                <x-icon name="chevron" :size="16" />
            </button>
            <ul class="dropdown-menu dropdown-menu-end account-menu">
                <li class="account-menu-head">
                    <span class="avatar" aria-hidden="true">{{ $initials }}</span>
                    <span class="min-w-0">
                        <span class="account-name text-truncate">{{ $user->name }}</span>
                        <span class="account-role text-truncate">{{ $user->email }}</span>
                    </span>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('account.password') }}"><x-icon name="key" :size="16" /> Change Password</a></li>
                <li><button type="button" class="dropdown-item d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#logoutModal"><x-icon name="logout" :size="16" /> Sign out</button></li>
            </ul>
        </div>
    </div>
</header>
