<x-guest-layout title="Privacy notice">
    <h1 class="auth-title">Privacy notice</h1>
    @include('privacy._notice')

    <a href="{{ auth()->check() ? url('/dashboard') : route('login') }}" class="btn btn-outline-secondary btn-sm-sies mt-2">← Back</a>
</x-guest-layout>
