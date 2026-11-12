{{-- The privacy notice text (Data Privacy Act of 2012, RA 10173). Shown on /privacy and in the sign-in page's window. --}}
@php
    $noticeSchool = \App\Support\SchoolBranding::hasRealName() ? \App\Support\SchoolBranding::name() : 'the school';
    try {
        $noticeContact = trim((string) \App\Models\Setting::get('registrar_contact', ''));
    } catch (\Throwable $e) {
        $noticeContact = '';
    }
@endphp
<p class="text-secondary small">How {{ $noticeSchool }} handles personal data in the SIES Campus Portal, under the Data Privacy Act of 2012 (Republic Act No. 10173).</p>

<div class="privacy-notice small">
    <h2>What we collect</h2>
    <p>Student details (name, birth date, gender, address, contact number, email, photo, program and year), parent or guardian names and contact numbers, enrollment records, attendance records, excuse letters, and submitted requirements.</p>

    <h2>Why</h2>
    <p>Only to register students, process enrollment, keep class schedules, monitor attendance, and contact a guardian when needed. The data is not sold or shared for marketing.</p>

    <h2>Who can see it</h2>
    <p>Registrar staff and administrators see student records. Teachers see the students in their own classes. Each student sees only their own record. Every sign-in and change is recorded.</p>

    <h2>How long it is kept</h2>
    <p>While the student is enrolled and afterwards for as long as school records must be kept. Records of students who leave are archived, not deleted.</p>

    <h2>Your rights</h2>
    <p>You may ask to see your data, have it corrected, or object to its use, and you may complain to the National Privacy Commission.
        @if (filter_var($noticeContact, FILTER_VALIDATE_EMAIL))
            Write to the registrar at <a href="mailto:{{ $noticeContact }}">{{ $noticeContact }}</a>.
        @elseif ($noticeContact !== '')
            Contact the registrar: {{ $noticeContact }}.
        @else
            Ask the registrar's office.
        @endif
    </p>
</div>
