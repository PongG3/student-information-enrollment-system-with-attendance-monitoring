<x-app-layout title="Change Password" subtitle="Choose a strong password that only you know.">
    <div class="card-sies p-4" style="max-width: 520px">
        @if ($forced)
            <div class="alert alert-warning">An administrator reset your password. Please choose a new one to continue.</div>
        @endif

        <form method="POST" action="{{ route('account.password.update') }}" novalidate>
            @csrf
            @method('PUT')

            <x-field name="current_password" type="password" label="Current password" required autocomplete="current-password" />
            <x-field name="password" type="password" label="New password" required autocomplete="new-password"
                help="At least 10 characters, with letters and numbers." />
            <x-field name="password_confirmation" type="password" label="Confirm new password" required autocomplete="new-password" />

            <button type="submit" class="btn btn-navy px-4">Change password</button>
        </form>
    </div>
</x-app-layout>
