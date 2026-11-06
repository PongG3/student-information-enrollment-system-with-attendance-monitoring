@php
    $role = auth()->user()->role;
    $workspace = config('sidebar.' . $role);
@endphp
<aside class="sidebar">
    <x-brand />
    <div class="sidebar-label">{{ $workspace['workspace'] }}</div>
    <nav class="nav flex-column">
        @foreach ($workspace['items'] as $item)
            <a href="{{ route($role . '.' . $item['slug']) }}"
               class="nav-link {{ request()->routeIs($role . '.' . $item['slug']) ? 'active' : '' }}">
                {{ $item['label'] }}
            </a>
        @endforeach
        <button type="button" class="nav-link" data-bs-toggle="modal" data-bs-target="#logoutModal">Logout</button>
    </nav>
</aside>
