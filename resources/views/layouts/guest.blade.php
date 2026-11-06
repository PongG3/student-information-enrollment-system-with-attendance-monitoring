<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>SIES Campus Portal</title>
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body>
    <div class="auth-shell">
        <section class="auth-hero d-none d-lg-flex">
            <x-brand />
            <div>
                <h2>A Smarter Way to manage your Academic Life.</h2>
                <p class="mt-3 opacity-75">Student information, enrollment and attendance working together in one secure workspace.</p>
            </div>
            <div class="auth-preview">
                <div class="small fw-semibold text-secondary mb-3">YOUR ACADEMIC OVERVIEW</div>
                <div class="row g-2">
                    <div class="col-6"><div class="p-3 rounded-3 bg-light"><small class="text-secondary">Enrollment</small><div class="fw-semibold">Track your classes</div></div></div>
                    <div class="col-6"><div class="p-3 rounded-3 bg-light"><small class="text-secondary">Attendance</small><div class="fw-semibold">Know your progress</div></div></div>
                </div>
            </div>
            <small class="opacity-75">Designed for students, teachers and registrars</small>
        </section>

        <section class="auth-panel">
            <div class="text-end small text-secondary">Need help? <span class="fw-semibold text-primary">Contact the registrar</span></div>
            <div class="auth-form">
                {{ $slot }}
            </div>
            <div class="small text-secondary border-top pt-3 mt-3">Protected academic access</div>
        </section>
    </div>
</body>
</html>
