@php
    // The sign-in page must always load, even before the database is migrated.
    try {
        $school = trim((string) \App\Models\Setting::get('school_name', ''));
        $contact = trim((string) \App\Models\Setting::get('registrar_contact', ''));
    } catch (\Throwable $e) {
        $school = '';
        $contact = '';
    }
    $showSchool = ! in_array($school, ['', 'School', 'School Name', 'Your School'], true);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign in · SIES Campus Portal</title>
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    <style>
        /* Sign-in pages only: easier to read on weak screens and projectors. */
        .auth-form .form-control { border-color: #9fb0c8; }
        .auth-form .form-control::placeholder { color: #5f6f86; opacity: 1; }
        .auth-form .form-control:focus { border-color: #174c78; box-shadow: 0 0 0 0.2rem rgba(23, 76, 120, 0.2); }
        .auth-form .btn-outline-secondary { border-color: #9fb0c8; color: #3c4d66; }
        .auth-roles .role-item { background: #f3f6fb; border-radius: 0.75rem; padding: 0.7rem 0.9rem; }
        .auth-roles .role-item + .role-item { margin-top: 0.5rem; }
    </style>
</head>
<body>
    <div class="auth-shell">
        <section class="auth-hero d-none d-lg-flex">
            <x-brand />
            <div>
                @if ($showSchool)
                    <div class="small text-uppercase fw-semibold opacity-75 mb-2" style="letter-spacing: 0.08em">{{ $school }}</div>
                @endif
                <h2>A smarter way to manage academic life.</h2>
                <p class="mt-3 opacity-75">Student information, enrollment and attendance working together in one secure workspace.</p>
            </div>
            <div class="auth-preview auth-roles">
                <div class="small fw-semibold text-secondary mb-3">WHO CAN SIGN IN</div>
                <div class="role-item"><div class="fw-semibold">Students</div><small class="text-secondary">See your classes, schedule and attendance</small></div>
                <div class="role-item"><div class="fw-semibold">Teachers</div><small class="text-secondary">Record and review attendance for your classes</small></div>
                <div class="role-item"><div class="fw-semibold">Registrar and administrators</div><small class="text-secondary">Manage records, enrollment and reports</small></div>
            </div>
            <small class="opacity-75">Use the account the registrar gave you.</small>
        </section>

        <section class="auth-panel">
            <div class="d-flex justify-content-between align-items-start gap-3">
                {{-- On phones the dark panel is hidden, so the name shows here. --}}
                <div class="d-lg-none">
                    <div class="fw-bold">SIES Campus Portal</div>
                    @if ($showSchool)<div class="small text-secondary">{{ $school }}</div>@endif
                </div>
                <div class="small text-secondary ms-auto text-end">
                    Need help?
                    @if ($contact === '')
                        <span class="fw-semibold" style="color: #0d2a52">Ask the registrar's office</span>
                    @elseif (filter_var($contact, FILTER_VALIDATE_EMAIL))
                        <a class="fw-semibold" href="mailto:{{ $contact }}">Contact the registrar</a>
                    @else
                        <span class="fw-semibold" style="color: #0d2a52">Contact the registrar: {{ $contact }}</span>
                    @endif
                </div>
            </div>
            <div class="auth-form">
                {{ $slot }}
            </div>
            <div class="small text-secondary border-top pt-3 mt-3">Authorized users only. Sign-ins and changes are recorded.</div>
        </section>
    </div>
</body>
</html>
