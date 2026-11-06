<x-guest-layout>
    <h1 class="auth-title">Welcome back</h1>
    <p class="text-secondary mb-4">Sign in to access your academic workspace.</p>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}" novalidate>
        @csrf

        <div class="mb-3">
            <label for="email" class="form-label fw-semibold small">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}"
                   class="form-control @error('email') is-invalid @enderror"
                   placeholder="Enter your school email" required autofocus autocomplete="username">
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label for="password" class="form-label fw-semibold small">Password</label>
            <input id="password" type="password" name="password"
                   class="form-control @error('password') is-invalid @enderror"
                   placeholder="Enter your password" required autocomplete="current-password">
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember">
                <label class="form-check-label small" for="remember">Keep me signed in</label>
            </div>
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="small fw-semibold text-decoration-none">Forgot password?</a>
            @endif
        </div>

        <button type="submit" class="btn btn-navy w-100">Sign in to portal</button>
    </form>
</x-guest-layout>
