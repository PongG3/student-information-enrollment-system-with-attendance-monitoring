{{--
    Shown on the page itself when the session runs out from being idle.
    resources/js/session-timeout.js opens it; the button leads to the sign-in page.
--}}
@php
    $minutes = \App\Support\SessionTimeout::minutes();
    $idle = $minutes >= 60 && $minutes % 60 === 0
        ? ($minutes / 60) . ' ' . \Illuminate\Support\Str::plural('hour', $minutes / 60)
        : $minutes . ' minutes';
@endphp
<div class="modal fade" id="sessionTimeoutModal" tabindex="-1" aria-labelledby="sessionTimeoutLabel" aria-describedby="sessionTimeoutText"
     aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false"
     data-session-timeout data-lifetime-minutes="{{ $minutes }}" data-login-url="{{ route('login') }}">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content text-center p-3">
            <div class="modal-body">
                <div class="session-timeout-icon mx-auto mb-3" aria-hidden="true"><x-icon name="alert" :size="22" /></div>
                <h2 class="h5 fw-bold" id="sessionTimeoutLabel">Session timed out</h2>
                <p class="text-secondary small mb-4" id="sessionTimeoutText">
                    For your security, you were signed out after {{ $idle }} of inactivity. Please sign in again to continue.
                </p>
                <a href="{{ route('login') }}" class="btn btn-navy w-100" data-session-relogin>Sign in again</a>
            </div>
        </div>
    </div>
</div>
