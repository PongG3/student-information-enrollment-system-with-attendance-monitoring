@php($editing = $user->exists)
<x-app-layout :title="$editing ? 'Edit User' : 'Add User'"
    :subtitle="$editing ? 'Update this account. Passwords are changed with Reset password.' : 'A temporary password is created for you to hand over once.'">
    <div class="card-sies p-4 card-form">
        <form method="POST" action="{{ $editing ? route('users.update', $user) : route('users.store') }}" novalidate>
            @csrf
            @if ($editing) @method('PUT') @endif

            <x-field name="name" label="Full name" :value="$user->name" required />
            <x-field name="email" type="email" label="Email address" :value="$user->email" required />

            @if ($user->role === 'student')
                <div class="mb-3">
                    <div class="form-label fw-semibold small">Role</div>
                    <div class="form-control-plaintext">Student (created from the student's record)</div>
                </div>
            @else
                <x-select name="role" label="Role" :options="$roleOptions" :value="$user->role" placeholder="Choose a role" required />
            @endif

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-navy px-4">{{ $editing ? 'Save changes' : 'Create user' }}</button>
                <a href="{{ route('users.index') }}" class="btn btn-outline-secondary px-4" data-dialog-close>Cancel</a>
            </div>
        </form>
    </div>
</x-app-layout>
