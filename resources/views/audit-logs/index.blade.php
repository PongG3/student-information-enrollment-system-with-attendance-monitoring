<x-app-layout title="Audit Logs" subtitle="A permanent record of important actions. Entries cannot be edited or deleted.">
    <div class="card-sies">
        <div class="card-header-sies">
            <form method="GET" class="filter-bar">
                <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search description">
                <select name="action" class="form-select">
                    <option value="">All actions</option>
                    @foreach ($actions as $action)
                        <option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>
                    @endforeach
                </select>
                <select name="role" class="form-select" aria-label="Role">
                    <option value="">All roles</option>
                    @foreach ($roles as $value => $label)
                        <option value="{{ $value }}" @selected(request('role') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="user" class="form-select">
                    <option value="">All users</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected((string) request('user') === (string) $user->id)>{{ $user->name }} ({{ \App\Support\RoleLabel::for($user->role) }})</option>
                    @endforeach
                </select>
                <input type="date" name="from" value="{{ request('from') }}" class="form-control" aria-label="From date">
                <input type="date" name="to" value="{{ request('to') }}" class="form-control" aria-label="To date">
                <button class="btn btn-outline-secondary btn-sm-sies">Filter</button>
                @if (request()->hasAny(['search', 'action', 'role', 'user', 'from', 'to']))
                    <a href="{{ route('audit-logs.index') }}" class="small">Clear</a>
                @endif
            </form>
        </div>

        @if ($logs->isEmpty())
            <x-empty-state icon="file" message="No log entries match your filters." />
        @else
            <div class="table-responsive" data-fit-viewport>
                <table class="table table-sies table-hover">
                    <thead><tr><th>When</th><th>User</th><th>Role</th><th>Action</th><th>Description</th><th>IP address</th></tr></thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr>
                                <td class="text-nowrap">{{ $log->created_at->format('M j, Y g:i A') }}</td>
                                <td>{{ $log->user?->name ?? 'System' }}</td>
                                <td>
                                    @if ($log->role_label)
                                        <span class="badge text-bg-light border">{{ $log->role_label }}</span>
                                    @else
                                        <span class="text-secondary small">—</span>
                                    @endif
                                </td>
                                <td><span class="badge text-bg-light border">{{ $log->action }}</span></td>
                                <td>
                                    {{ $log->description }}
                                    @if ($log->old_values || $log->new_values)
                                        <details class="small text-secondary mt-1">
                                            <summary>Details</summary>
                                            @if ($log->old_values)<div><strong>Before:</strong> {{ json_encode($log->old_values) }}</div>@endif
                                            @if ($log->new_values)<div><strong>After:</strong> {{ json_encode($log->new_values) }}</div>@endif
                                        </details>
                                    @endif
                                </td>
                                <td class="text-secondary small">{{ $log->ip_address }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-table-footer :items="$logs" noun="entry" />
        @endif
    </div>
</x-app-layout>
