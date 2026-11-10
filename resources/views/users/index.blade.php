<x-app-layout title="User Management" subtitle="Create staff accounts, assign roles, and control who can sign in.">
    <x-slot:actions>
        <a href="{{ route('users.create') }}" class="btn btn-navy"><x-icon name="plus" :size="16" /> Add user</a>
    </x-slot:actions>

    @if (session('temp_password'))
        <div class="alert alert-warning">
            <div class="fw-semibold mb-1">Temporary password for {{ session('temp_for') }}</div>
            <div class="temp-password user-select-all">{{ session('temp_password') }}</div>
            <div class="small mt-2">Copy it now and give it to the user in person. It is shown only once, and they must choose a new password at their first sign-in.</div>
        </div>
    @endif

    <div class="card-sies">
        <div class="card-header-sies">
            <form method="GET" class="filter-bar">
                <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search name or email">
                <select name="role" class="form-select">
                    <option value="">All roles</option>
                    @foreach ($roleLabels as $value => $label)
                        <option value="{{ $value }}" @selected(request('role') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="status" class="form-select">
                    <option value="">All statuses</option>
                    <option value="active" @selected(request('status') === 'active')>Active</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                </select>
                <button class="btn btn-outline-secondary btn-sm-sies">Filter</button>
                @if (request()->hasAny(['search', 'role', 'status']))
                    <a href="{{ route('users.index') }}" class="small">Clear</a>
                @endif
            </form>
        </div>

        @if ($users->isEmpty())
            <x-empty-state icon="user" message="No users match your filters." />
        @else
            <div class="table-responsive" data-fit-viewport>
                <table class="table table-sies table-hover">
                    <thead>
                        <tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th class="text-end">Actions</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td class="fw-semibold">{{ $user->name }}
                                    @if ($user->is(auth()->user())) <span class="text-secondary small">(you)</span> @endif
                                </td>
                                <td>{{ $user->email }}</td>
                                <td>{{ $roleLabels[$user->role] ?? ucfirst($user->role) }}</td>
                                <td><x-status-badge :value="$user->is_active ? 'active' : 'inactive'" /></td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('users.edit', $user) }}" class="btn btn-outline-secondary btn-sm-sies">Edit</a>
                                    @unless ($user->is(auth()->user()))
                                        <button type="button" class="btn btn-outline-secondary btn-sm-sies"
                                            data-bs-toggle="modal" data-bs-target="#confirmModal"
                                            data-confirm-title="Reset password?"
                                            data-confirm-message="{{ $user->name }} will be signed out and given a new temporary password."
                                            data-confirm-action="{{ route('users.reset-password', $user) }}"
                                            data-confirm-method="POST" data-confirm-button="Reset password">Reset password</button>
                                        <button type="button" class="btn btn-outline-{{ $user->is_active ? 'danger' : 'success' }} btn-sm-sies"
                                            data-bs-toggle="modal" data-bs-target="#confirmModal"
                                            data-confirm-title="{{ $user->is_active ? 'Deactivate account?' : 'Activate account?' }}"
                                            data-confirm-message="{{ $user->is_active ? $user->name . ' will be signed out and will not be able to sign in.' : $user->name . ' will be able to sign in again.' }}"
                                            data-confirm-action="{{ route('users.status', $user) }}"
                                            data-confirm-method="PATCH"
                                            data-confirm-variant="{{ $user->is_active ? 'danger' : 'primary' }}"
                                            data-confirm-button="{{ $user->is_active ? 'Deactivate' : 'Activate' }}">{{ $user->is_active ? 'Deactivate' : 'Activate' }}</button>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-table-footer :items="$users" noun="user" />
        @endif
    </div>
</x-app-layout>
