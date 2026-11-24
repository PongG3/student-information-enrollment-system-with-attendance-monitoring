<x-guest-layout>
    <h1 class="auth-title">Welcome back</h1>
    <p class="text-secondary mb-4">Sign in to access your academic workspace.</p>

    @if ($timedOut ?? false)
        <div class="alert alert-warning d-flex align-items-start gap-2 py-2 small" role="alert">
            <x-icon name="alert" :size="16" />
            <div><strong>Session timed out.</strong> You were signed out after being idle. Please sign in again.</div>
        </div>
    @elseif (session('status'))
        <div class="alert alert-info py-2 small" role="status">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}" novalidate>
        @csrf

        <div class="mb-3">
            <label for="email" class="form-label fw-semibold small">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}"
                   class="form-control @error('email') is-invalid @enderror"
                   placeholder="e.g. juan.delacruz@school.edu.ph" required autofocus
                   autocomplete="username" autocapitalize="none" spellcheck="false" inputmode="email">
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-4">
            <div class="d-flex justify-content-between align-items-baseline">
                <label for="password" class="form-label fw-semibold small">Password</label>
                <a href="{{ route('password.request') }}" class="small fw-semibold text-decoration-none">Forgot password?</a>
            </div>
            {{-- The eye sits inside the field's right edge; the label already says what to type. --}}
            <div class="password-field">
                <input id="password" type="password" name="password"
                       class="form-control @error('password') is-invalid @enderror"
                       required autocomplete="current-password">
                <button type="button" class="password-eye" id="togglePassword"
                        aria-label="Show password" aria-pressed="false" aria-controls="password" title="Show password">
                    <span data-eye="show"><x-icon name="eye" :size="18" /></span>
                    <span data-eye="hide" hidden><x-icon name="eye-off" :size="18" /></span>
                </button>
            </div>
            @error('password')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="btn btn-navy w-100">Sign in to portal</button>

        <p class="text-center text-secondary small mt-4 mb-0">
            No access to your email? Ask the registrar or an administrator to reset your password.
        </p>
    </form>

    <script>
        (function () {
            var button = document.getElementById('togglePassword');
            var input = document.getElementById('password');
            if (!button || !input) { return; }
            button.addEventListener('click', function () {
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                button.querySelector('[data-eye="show"]').hidden = show;
                button.querySelector('[data-eye="hide"]').hidden = !show;
                button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                button.title = show ? 'Hide password' : 'Show password';
                button.setAttribute('aria-pressed', show ? 'true' : 'false');
                input.focus();
            });
        })();
    </script>
</x-guest-layout>
