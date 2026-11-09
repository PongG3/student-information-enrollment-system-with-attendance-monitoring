<x-app-layout :title="$student->full_name" :subtitle="$student->student_number . ' · ' . $student->program->name"
    :back="route('students.index')" back-label="Back to student records">
    @if (session('temp_password'))
        <div class="alert alert-warning">
            <div class="fw-semibold mb-1">Temporary password for {{ session('temp_for') }}</div>
            <div class="temp-password user-select-all">{{ session('temp_password') }}</div>
            <div class="small mt-2">Copy it now and give it to the student in person. It is shown only once, and they must choose a new password at their first sign-in.</div>
        </div>
    @endif

    @if ($student->trashed())
        <div class="alert alert-secondary d-flex justify-content-between align-items-center">
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
        <div class="col-lg-4">
            <div class="card-sies p-4 mb-4">
                <div class="text-center mb-3">
                    @if ($student->photo_path)
                        <img src="{{ route('students.photo', $student) }}" alt="Photo of {{ $student->full_name }}"
                             class="rounded-circle" style="width: 120px; height: 120px; object-fit: cover">
                    @else
                        <div class="rounded-circle bg-light text-secondary d-inline-grid fw-bold" style="width: 120px; height: 120px; display: inline-grid; place-items: center; font-size: 2rem">
                            {{ strtoupper(substr($student->first_name, 0, 1) . substr($student->last_name, 0, 1)) }}
                        </div>
                    @endif
                    <h2 class="h5 fw-bold mt-3 mb-1">{{ $student->full_name }}</h2>
                    <div class="text-secondary small mb-2">{{ $student->student_number }}</div>
                    @if ($student->trashed()) <x-status-badge value="inactive" label="Archived" />
                    @else <x-status-badge :value="$student->status" />
                    @endif
                </div>

                <dl class="small mb-0">
                    <dt class="text-secondary">Program</dt><dd>{{ $student->program->code }} · {{ $student->program->name }}</dd>
                    <dt class="text-secondary">Year level / type</dt><dd>Year {{ $student->year_level }} · {{ ucfirst($student->student_type) }}</dd>
                    <dt class="text-secondary">Block section</dt><dd>{{ $student->block_section ?? 'Not assigned' }}</dd>
                    <dt class="text-secondary">Gender</dt><dd>{{ $student->gender }}</dd>
                    <dt class="text-secondary">Date of birth</dt><dd>{{ $student->birth_date->format('M j, Y') }}</dd>
                    <dt class="text-secondary">Email</dt><dd>{{ $student->email ?: '—' }}</dd>
                    <dt class="text-secondary">Contact number</dt><dd>{{ $student->contact_number ?: '—' }}</dd>
                    <dt class="text-secondary">Address</dt><dd>{{ $student->address ?: '—' }}</dd>
                    <dt class="text-secondary">Previous school</dt><dd class="mb-0">{{ $student->previous_school ?: '—' }}</dd>
                </dl>

                @unless ($student->trashed())
                    <div class="d-flex gap-2 mt-4">
                        <a href="{{ route('students.edit', $student) }}" class="btn btn-outline-secondary btn-sm-sies">Edit</a>
                        @if ($student->status === 'active')
                            <a href="{{ route('enrollments.create', ['student' => $student->id]) }}" class="btn btn-navy btn-sm-sies">Add enrollment</a>
                        @endif
                        <button type="button" class="btn btn-outline-danger btn-sm-sies"
                            data-bs-toggle="modal" data-bs-target="#confirmModal"
                            data-confirm-title="Archive student?"
                            data-confirm-message="{{ $student->full_name }} is hidden from lists but all history is kept. You can restore them later."
                            data-confirm-action="{{ route('students.archive', $student) }}"
                            data-confirm-method="DELETE" data-confirm-variant="danger"
                            data-confirm-button="Archive">Archive</button>
                    </div>
                @endunless
            </div>

            @unless ($student->trashed())
                <div class="card-sies p-4 mb-4">
                    <h2 class="h6 fw-bold">Portal account</h2>
                    @if ($student->user)
                        <dl class="small mb-3">
                            <dt class="text-secondary">Sign-in email</dt><dd>{{ $student->user->email }}</dd>
                            <dt class="text-secondary">Status</dt>
                            <dd class="mb-0">
                                <x-status-badge :value="$student->user->is_active ? 'active' : 'inactive'" />
                                @if ($student->user->must_change_password)<div class="text-secondary mt-1">Has not chosen their own password yet.</div>@endif
                            </dd>
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
                        <p class="small text-secondary">No sign-in account yet. Create one so this student can see their own classes, schedule and attendance.</p>
                        <form method="POST" action="{{ route('students.account.store', $student) }}" novalidate>
                            @csrf
                            <x-field name="email" type="email" label="Sign-in email" :value="old('email', $student->email)" required />
                            <button type="submit" class="btn btn-navy btn-sm-sies">Create account</button>
                        </form>
                    @endif
                </div>
            @endunless
        </div>

        <div class="col-lg-8">
            <div class="card-sies mb-4">
                <div class="card-header-sies"><h2 class="h6 fw-bold mb-0">Parents / guardians</h2></div>
                @if ($student->guardians->isEmpty())
                    <x-empty-state message="No guardian recorded." />
                @else
                    <div class="table-responsive">
                        <table class="table table-sies">
                            <thead><tr><th>Name</th><th>Relationship</th><th>Contact</th><th>Emergency</th></tr></thead>
                            <tbody>
                                @foreach ($student->guardians->sortByDesc('is_emergency_contact') as $guardian)
                                    <tr>
                                        <td class="fw-semibold">{{ $guardian->full_name }}</td>
                                        <td>{{ $guardian->relationship }}</td>
                                        <td>{{ $guardian->contact_number }}</td>
                                        <td>{{ $guardian->is_emergency_contact ? 'Yes' : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="card-sies mb-4">
                <div class="card-header-sies"><h2 class="h6 fw-bold mb-0">Requirements</h2></div>
                @if ($requirements->isEmpty())
                    <x-empty-state message="No requirements are set up." />
                @else
                    <div class="table-responsive">
                        <table class="table table-sies align-middle">
                            <thead><tr><th>Requirement</th><th>Status</th><th>Verified by</th>@unless ($student->trashed())<th class="text-end">Actions</th>@endunless</tr></thead>
                            <tbody>
                                @foreach ($requirements as $row)
                                    <tr>
                                        <td class="fw-semibold">{{ $row->requirement->name }}
                                            @unless ($row->requirement->is_required)<span class="text-secondary small">(optional)</span>@endunless
                                        </td>
                                        <td><x-status-badge :value="$row->status" /></td>
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

            <div class="card-sies">
                <div class="card-header-sies"><h2 class="h6 fw-bold mb-0">Enrollment history</h2></div>
                @if ($enrollments->isEmpty())
                    <x-empty-state message="This student has not requested any class yet." />
                @else
                    <div class="table-responsive">
                        <table class="table table-sies">
                            <thead><tr><th>Class</th><th>Subject</th><th>Term</th><th>Status</th></tr></thead>
                            <tbody>
                                @foreach ($enrollments as $enrollment)
                                    <tr>
                                        <td class="fw-semibold"><a href="{{ route('classes.show', $enrollment->classOffering) }}" class="text-decoration-none">{{ $enrollment->classOffering->class_code }}</a></td>
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
    </div>
</x-app-layout>
