<header class="topbar">
    <input type="search" class="form-control topbar-search" placeholder="Search records, classes or announcements..." disabled>
    <div class="text-end lh-sm">
        <div class="fw-semibold">{{ auth()->user()->name }}</div>
        <small class="text-secondary">{{ config('sidebar.' . auth()->user()->role . '.role_label') }}</small>
    </div>
</header>
