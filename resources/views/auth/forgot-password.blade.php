<x-guest-layout title="Forgot password">
    <h1 class="auth-title">Forgot your password?</h1>
    <p class="text-secondary mb-4">Enter your student number. We will email a link to choose a new password to the email on your account.</p>

    @if (session('status'))
        <div class="alert alert-success d-flex align-items-start gap-2 py-2 small" role="status">
            <x-icon name="check" :size="16" />
            <div>{{ session('status') }}</div>
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" novalidate>
        @csrf
        <div class="mb-4">
            <label for="identifier" class="form-label fw-semibold small">Student number <span class="text-secondary fw-normal">or staff email</span></label>
            <input id="identifier" type="text" name="identifier" value="{{ old('identifier') }}"
                   class="form-control @error('identifier') is-invalid @enderror"
                   placeholder="e.g. 2026-0001" maxlength="255" required autofocus
                   autocomplete="username" autocapitalize="none" spellcheck="false">
            @error('identifier')<div class="invalid-feedback">{{ $message }}</div>@enderror
            @error('email')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            <div class="form-text">Students: your student number. Teachers and staff: the email you sign in with.</div>
        </div>

        <button type="submit" class="btn btn-navy w-100">Email me a reset link</button>

        <p class="text-center small mt-4 mb-1">
            <a href="{{ route('login') }}" class="fw-semibold text-decoration-none">← Back to sign in</a>
        </p>
        <p class="text-center text-secondary small mb-0">
            No access to your email? Ask the registrar or an administrator to reset your password.
        </p>
    </form>
</x-guest-layout>
