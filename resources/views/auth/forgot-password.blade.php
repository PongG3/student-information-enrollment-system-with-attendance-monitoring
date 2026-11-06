<x-guest-layout>
    <h1 class="auth-title">Forgot Password</h1>
    <p class="text-secondary mb-4">Enter your school email and we will send reset instructions.</p>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" novalidate>
        @csrf
        <div class="mb-4">
            <label for="email" class="form-label fw-semibold small">School email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}"
                   class="form-control @error('email') is-invalid @enderror"
                   placeholder="Enter your school email" required autofocus>
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn btn-navy w-100">Send access instructions</button>
        <a href="{{ route('login') }}" class="d-block text-center mt-3 small fw-semibold text-decoration-none">← Back to sign in</a>
    </form>
</x-guest-layout>
