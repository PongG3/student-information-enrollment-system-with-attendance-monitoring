<x-guest-layout title="Choose your password">
    <h1 class="auth-title">Choose your password</h1>
    <p class="text-secondary mb-4">For {{ $user->email }}. Use at least 12 characters with upper and lower case letters and a number.</p>

    {{-- The signed address (with its query) is kept as the form's action. --}}
    <form method="POST" action="{{ request()->fullUrl() }}" novalidate>
        @csrf
        <div class="mb-3">
            <label for="password" class="form-label fw-semibold small">New password</label>
            <input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror"
                   required autofocus autocomplete="new-password">
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-4">
            <label for="password_confirmation" class="form-label fw-semibold small">Type it again</label>
            <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" required autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-navy w-100">Save password</button>
    </form>
</x-guest-layout>
