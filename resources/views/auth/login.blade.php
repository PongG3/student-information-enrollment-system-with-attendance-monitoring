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
                   placeholder="Enter your school email" required autofocus
                   autocomplete="username" autocapitalize="none" spellcheck="false" inputmode="email">
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-4">
            <label for="password" class="form-label fw-semibold small">Password</label>
            <div class="input-group">
                <input id="password" type="password" name="password"
                       class="form-control @error('password') is-invalid @enderror"
                       placeholder="Enter your password" required autocomplete="current-password">
                <button type="button" class="btn btn-outline-secondary" id="togglePassword"
                        aria-label="Show password" aria-pressed="false" aria-controls="password">Show</button>
            </div>
            @error('password')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="btn btn-navy w-100">Sign in to portal</button>

        <p class="text-center text-secondary small mt-4 mb-0">
            Forgot your password? Ask the registrar or an administrator to reset it.
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
                button.textContent = show ? 'Hide' : 'Show';
                button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                button.setAttribute('aria-pressed', show ? 'true' : 'false');
                input.focus();
            });
        })();
    </script>
</x-guest-layout>
