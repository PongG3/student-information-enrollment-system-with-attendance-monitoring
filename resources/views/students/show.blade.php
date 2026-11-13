@php
    $details = [
        ['Program', $student->program->code . ' · ' . $student->program->name, true],
        ['Year level / type', 'Year ' . $student->year_level . ' · ' . ucfirst($student->student_type), false],
        ['Block section', $student->block_section ?? 'Not assigned', false],
        ['Gender', $student->gender, false],
        ['Date of birth', $student->birth_date->format('M j, Y'), false],
        ['Email', $student->email ?: '—', true],
        ['Contact number', $student->contact_number ?: '—', false],
        ['Previous school', $student->previous_school ?: '—', false],
        ['Address', $student->address ?: '—', true],
    ];
    $verified = $requirements->where('status', 'verified')->count();
@endphp
<x-app-layout :title="$student->full_name" :subtitle="$student->student_number . ' · ' . $student->program->name"
    :back="route('students.index')" back-label="Back to student records">
    @unless ($student->trashed())
        <x-slot:actions>
            <a href="{{ route('students.personal-data', $student) }}" data-download class="btn btn-outline-secondary" title="Everything held about this student, for a data privacy request">
                <x-icon name="file" :size="16" /> Personal data (PDF)
            </a>
            <a href="{{ route('students.edit', $student) }}" data-dialog data-dialog-size="xl" class="btn btn-outline-secondary">Edit</a>
            @if ($student->status === 'active')
                <a href="{{ route('enrollments.create', ['student' => $student->id]) }}" data-dialog data-dialog-size="xl" class="btn btn-navy"><x-icon name="plus" :size="16" /> Add enrollment</a>
            @endif
            <button type="button" class="btn btn-outline-danger"
                data-bs-toggle="modal" data-bs-target="#confirmModal"
                data-confirm-title="Archive student?"
                data-confirm-message="{{ $student->full_name }} is hidden from lists but all history is kept. You can restore them later."
                data-confirm-action="{{ route('students.archive', $student) }}"
                data-confirm-method="DELETE" data-confirm-variant="danger"
                data-confirm-button="Archive">Archive</button>
        </x-slot:actions>
    @endunless

    @if (session('temp_password'))
        <div class="alert alert-warning d-flex flex-wrap align-items-center gap-3" role="alert">
            <div class="flex-grow-1">
                <div class="fw-semibold mb-1">Temporary password for {{ session('temp_for') }}</div>
                <div class="temp-password user-select-all" id="tempPassword">{{ session('temp_password') }}</div>
                <div class="small mt-2">Copy it now and give it to the student in person. It is shown only once, and they must choose a new password at their first sign-in.</div>
            </div>
            <button type="button" class="btn btn-outline-secondary btn-sm-sies" data-copy="#tempPassword">Copy</button>
        </div>
    @endif

    @if ($student->trashed())
        <div class="alert alert-secondary d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span>This student is archived. The record is read-only and kept for academic history.</span>
            <button type="button" class="btn btn-outline-success btn-sm-sies"
                data-bs-toggle="modal" data-bs-target="#confirmModal"
                data-confirm-title="Restore student?"
                data-confirm-message="{{ $student->full_name }} will appear in the student list again."
                data-confirm-action="{{ route('students.restore', $student) }}"
                data-confirm-button="Restore">Restore</button>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-xl-4 col-lg-5">
            <div class="card-sies p-4 mb-4">
                <div class="profile-head">
                    @if ($student->photo_path)
                        <img src="{{ route('students.photo', $student) }}" alt="Photo of {{ $student->full_name }}" class="profile-avatar profile-photo">
                    @else
                        <div class="profile-avatar" aria-hidden="true">{{ strtoupper(substr($student->first_name, 0, 1) . substr($student->last_name, 0, 1)) }}</div>
                    @endif
                    <div class="min-w-0">
                        <h2 class="h5 fw-bold mb-1">{{ $student->full_name }}</h2>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="text-secondary small">{{ $student->student_number }}</span>
                            @if ($student->trashed()) <x-status-badge value="inactive" label="Archived" />
                            @else <x-status-badge :value="$student->status" />
                            @endif
                        </div>
                    </div>
                </div>
                @if ($student->pending_photo_path)
                    {{-- A photo the student submitted: approve it as the official ID photo, or reject it. --}}
                    <div class="photo-review mb-3">
                        <img src="{{ route('students.photo.pending', $student) }}" alt="Photo submitted by {{ $student->full_name }}" class="photo-review-img">
                        <div class="min-w-0">
                            <div class="fw-semibold small">New photo waiting for approval</div>
                            <div class="small text-secondary mb-2">Sent {{ $student->pending_photo_at?->format('M j, Y g:i A') }}. Check it is a clear, formal ID photo.</div>
                            <div class="d-flex gap-2">
                                <form method="POST" action="{{ route('students.photo.approve', $student) }}">@csrf<button class="btn btn-outline-success btn-sm-sies">Approve</button></form>
                                <button type="button" class="btn btn-quiet-danger btn-sm-sies"
                                    data-bs-toggle="modal" data-bs-target="#reasonModal"
                                    data-reason-title="Reject this photo?"
                                    data-reason-message="Tell {{ $student->first_name }} what to fix (for example: not a formal photo, face not clear). They get it by email and on their profile."
                                    data-reason-action="{{ route('students.photo.reject', $student) }}"
                                    data-reason-button="Reject photo">Reject</button>
                            </div>
                        </div>
                    </div>
                @endif
                <dl class="profile-details">
                    @foreach ($details as [$label, $value, $wide])
                        <div @class(['is-wide' => $wide])><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                    @endforeach
                </dl>
            </div>

        </div>

        <div class="col-xl-8 col-lg-7">
            @php
                $tabs = [
                    'requirements' => ['Requirements', 'file', $verified . '/' . $requirements->count()],
                    'enrollments' => ['Enrollment history', 'clipboard', $enrollments->count()],
                    'guardians' => ['Guardians', 'students', $student->guardians->count()],
                ];
                if (! $student->trashed()) {
                    $tabs['account'] = ['Portal account', 'key', $student->user ? ($student->user->is_active ? 'active' : 'off') : 'none'];
                }
            @endphp
            <ul class="form-tabs" role="tablist" aria-label="Student record">
                @foreach ($tabs as $key => [$label, $icon, $count])
                    <li role="presentation">
                        <button type="button" role="tab" id="tab-{{ $key }}" data-bs-toggle="tab" data-bs-target="#pane-{{ $key }}"
                            aria-controls="pane-{{ $key }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" @class(['form-tab', 'active' => $loop->first])>
                            <x-icon :name="$icon" :size="16" /> {{ $label }} <span class="status-tab-count">{{ $count }}</span>
                        </button>
                    </li>
                @endforeach
            </ul>
            <div class="tab-content">
            <div class="tab-pane fade show active" id="pane-requirements" role="tabpanel" aria-labelledby="tab-requirements" tabindex="0">
                <div class="card-sies">
                    <div class="card-header-sies">
                        <h2 class="h6 fw-bold mb-0">Requirements</h2>
                        @if ($requirements->isNotEmpty())
                            <span class="small fw-semibold">{{ $verified }} of {{ $requirements->count() }} verified</span>
                        @endif
                    </div>
                    @if ($requirements->isEmpty())
                        <x-empty-state icon="file" message="No requirements are set up." />
                    @else
                        <div class="table-responsive">
                            <table class="table table-sies align-middle">
                                <thead><tr><th scope="col">Requirement</th><th scope="col">Status</th><th scope="col">Verified by</th>@unless ($student->trashed())<th scope="col" class="text-end">Actions</th>@endunless</tr></thead>
                                <tbody>
                                    @foreach ($requirements as $row)
                                        <tr>
                                            <td class="fw-semibold">{{ $row->requirement->name }}
                                                @unless ($row->requirement->is_required)<span class="text-secondary small fw-normal">(optional)</span>@endunless
                                            </td>
                                            <td>
                                                <x-status-badge :value="$row->status" />
                                                @if ($row->file_path)
                                                    <a href="{{ route('requirements.file', $row) }}" class="small ms-1">Student's scan</a>
                                                @endif
                                            </td>
                                            <td class="small text-secondary">
                                                @if ($row->verified_at) {{ $row->verifier?->name ?? 'Staff' }} · {{ $row->verified_at->format('M j, Y') }} @else — @endif
                                            </td>
                                            @unless ($student->trashed())
                                                <td class="text-end text-nowrap">
                                                    @foreach (['submitted' => ['Mark submitted', 'secondary'], 'verified' => ['Verify', 'success'], 'pending' => ['Reset', 'secondary']] as $status => [$label, $variant])
                                                        @if ($row->status !== $status)
                                                            <form method="POST" class="d-inline"
                                                                  action="{{ route('students.requirements.update', [$student, $row->requirement]) }}">
                                                                @csrf
                                                                <input type="hidden" name="status" value="{{ $status }}">
                                                                <button class="btn btn-outline-{{ $variant }} btn-sm-sies">{{ $label }}</button>
                                                            </form>
                                                        @endif
                                                    @endforeach
                                                </td>
                                            @endunless
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
            <div class="tab-pane fade" id="pane-enrollments" role="tabpanel" aria-labelledby="tab-enrollments" tabindex="0">
                <div class="card-sies">
                    @php
                        // Terms with officially enrolled classes: each has a Certificate of Registration.
                        $corTerms = $enrollments->where('status', 'enrolled')->map(fn ($e) => $e->classOffering->term)
                            ->unique('id')->sortByDesc('start_date')->values();
                    @endphp
                    <div class="card-header-sies">
                        <h2 class="h6 fw-bold mb-0">Enrollment history</h2>
                        @if ($corTerms->isNotEmpty())
                            <div class="dropdown">
                                <button type="button" class="btn btn-outline-secondary btn-sm-sies dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                    <x-icon name="file" :size="14" /> Registration form
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><h3 class="dropdown-header">Certificate of Registration (PDF)</h3></li>
                                    @foreach ($corTerms as $corTerm)
                                        <li><a class="dropdown-item" data-download href="{{ route('students.registration-form', [$student, 'term' => $corTerm->id]) }}">{{ $corTerm->name }} · {{ $corTerm->academicYear->name }}</a></li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                    @if ($enrollments->isEmpty())
                        <x-empty-state icon="clipboard" message="This student has not requested any class yet.">
                            @if (! $student->trashed() && $student->status === 'active')
                                <a href="{{ route('enrollments.create', ['student' => $student->id]) }}" data-dialog data-dialog-size="xl" class="btn btn-navy btn-sm-sies">Add enrollment</a>
                            @endif
                        </x-empty-state>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sies table-hover">
                                <thead><tr><th scope="col">Class</th><th scope="col">Subject</th><th scope="col">Term</th><th scope="col">Status</th></tr></thead>
                                <tbody>
                                    @foreach ($enrollments as $enrollment)
                                        <tr>
                                            <td class="fw-semibold"><a href="{{ route('classes.show', $enrollment->classOffering) }}">{{ $enrollment->classOffering->class_code }}</a></td>
                                            <td>{{ $enrollment->classOffering->subject->code }} · {{ $enrollment->classOffering->subject->title }}</td>
                                            <td>{{ $enrollment->classOffering->term->name }} · {{ $enrollment->classOffering->term->academicYear->name }}</td>
                                            <td>
                                                <x-status-badge :value="$enrollment->status" />
                                                @if ($enrollment->status_reason)<div class="text-secondary small">{{ $enrollment->status_reason }}</div>@endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
            <div class="tab-pane fade" id="pane-guardians" role="tabpanel" aria-labelledby="tab-guardians" tabindex="0">
                <div class="card-sies">
                    <div class="card-header-sies"><h2 class="h6 fw-bold mb-0">Parents / guardians</h2></div>
                    @if ($student->guardians->isEmpty())
                        <x-empty-state icon="students" message="No guardian recorded." />
                    @else
                        <div class="table-responsive">
                            <table class="table table-sies">
                                <thead><tr><th scope="col">Name</th><th scope="col">Relationship</th><th scope="col">Contact</th><th scope="col">Emergency</th></tr></thead>
                                <tbody>
                                    @foreach ($student->guardians->sortByDesc('is_emergency_contact') as $guardian)
                                        <tr>
                                            <td class="fw-semibold">{{ $guardian->full_name }}</td>
                                            <td>{{ $guardian->relationship }}</td>
                                            <td>{{ $guardian->contact_number }}</td>
                                            <td>@if ($guardian->is_emergency_contact)<x-status-badge value="current" label="Emergency" />@else — @endif</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
            @unless ($student->trashed())
            <div class="tab-pane fade" id="pane-account" role="tabpanel" aria-labelledby="tab-account" tabindex="0">
                    <section class="card-sies p-4" aria-labelledby="account-heading">
                        <div class="section-head mb-3">
                            <span class="section-icon" aria-hidden="true"><x-icon name="key" :size="18" /></span>
                            <div>
                                <h2 class="section-title" id="account-heading">Portal account</h2>
                                <p class="section-help">Lets the student see their own classes, schedule and attendance.</p>
                            </div>
                        </div>
                        @if ($student->user)
                            <dl class="profile-details mb-3">
                                <div class="is-wide"><dt>Sign-in email</dt><dd>{{ $student->user->email }}</dd></div>
                                <div class="is-wide"><dt>Status</dt>
                                    <dd>
                                        <x-status-badge :value="$student->user->is_active ? 'active' : 'inactive'" />
                                        @if ($student->user->must_change_password)<div class="text-secondary small mt-1">Has not chosen their own password yet.</div>@endif
                                    </dd>
                                </div>
                            </dl>
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm-sies"
                                    data-bs-toggle="modal" data-bs-target="#confirmModal"
                                    data-confirm-title="Reset password?"
                                    data-confirm-message="A new temporary password is created. The student is signed out everywhere and must choose a new password."
                                    data-confirm-action="{{ route('students.account.reset', $student) }}"
                                    data-confirm-button="Reset password">Reset password</button>
                                <button type="button" class="btn btn-outline-{{ $student->user->is_active ? 'danger' : 'success' }} btn-sm-sies"
                                    data-bs-toggle="modal" data-bs-target="#confirmModal"
                                    data-confirm-title="{{ $student->user->is_active ? 'Deactivate account?' : 'Activate account?' }}"
                                    data-confirm-message="{{ $student->user->is_active ? 'The student can no longer sign in.' : 'The student can sign in again.' }}"
                                    data-confirm-action="{{ route('students.account.status', $student) }}"
                                    data-confirm-button="{{ $student->user->is_active ? 'Deactivate' : 'Activate' }}"
                                    @if ($student->user->is_active) data-confirm-variant="danger" @endif>{{ $student->user->is_active ? 'Deactivate' : 'Activate' }}</button>
                            </div>
                        @else
                            <p class="small text-secondary">No sign-in account yet.</p>
                            <form method="POST" action="{{ route('students.account.store', $student) }}" novalidate>
                                @csrf
                                <x-field name="email" type="email" label="Sign-in email" :value="old('email', $student->email)" required />
                                <button type="submit" class="btn btn-navy btn-sm-sies">Create account</button>
                            </form>
                        @endif
                    </section>
            </div>
            @endunless
            </div>
        </div>
    </div>

    <x-reason-modal />
</x-app-layout>
