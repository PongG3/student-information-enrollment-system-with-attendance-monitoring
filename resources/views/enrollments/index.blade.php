<x-app-layout title="Enrollment Requests" subtitle="Record requests for students, then approve, enroll, reject or cancel them.">
    <x-slot:actions>
        <a href="{{ route('enrollments.create') }}" class="btn btn-navy"><x-icon name="plus" :size="16" /> New request</a>
    </x-slot:actions>

    @if ($errors->has('enrollment'))
        <div class="alert alert-danger">
            <div class="fw-semibold mb-1">That could not be done:</div>
            <ul class="mb-0 ps-3">
                @foreach ($errors->get('enrollment') as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <ul class="status-tabs mb-3" aria-label="Filter by status">
        <li class="nav-item">
            <a class="nav-link {{ request('status') ? '' : 'active' }}"
               href="{{ route('enrollments.index', array_filter(['term' => $termId, 'search' => request('search')])) }}">
                All <span class="status-tab-count">{{ array_sum($counts) }}</span>
            </a>
        </li>
        @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'enrolled' => 'Enrolled', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'] as $status => $label)
            <li class="nav-item">
                <a class="nav-link {{ request('status') === $status ? 'active' : '' }}"
                   href="{{ route('enrollments.index', array_filter(['term' => $termId, 'status' => $status, 'search' => request('search')])) }}">
                    {{ $label }} <span class="status-tab-count">{{ $counts[$status] ?? 0 }}</span>
                </a>
            </li>
        @endforeach
    </ul>

    <div class="card-sies">
        <div class="card-header-sies">
            <form method="GET" class="filter-bar">
                <select name="term" class="form-select" aria-label="Term" onchange="this.form.submit()">
                    @foreach ($terms as $term)
                        <option value="{{ $term->id }}" @selected((string) $termId === (string) $term->id)>
                            {{ $term->name }} · {{ $term->academicYear->name }}{{ $term->is_current ? ' (current)' : '' }}
                        </option>
                    @endforeach
                </select>
                @if (request('status')) <input type="hidden" name="status" value="{{ request('status') }}"> @endif
                <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search student, class or subject" aria-label="Search student, class or subject">
                <button class="btn btn-outline-secondary btn-sm-sies">Search</button>
                @if (request()->hasAny(['search', 'status']))
                    <a href="{{ route('enrollments.index', ['term' => $termId]) }}" class="small">Clear</a>
                @endif
            </form>
        </div>

        @if ($enrollments->isEmpty())
            <x-empty-state icon="clipboard" message="No enrollment requests match.">
                <a href="{{ route('enrollments.create') }}" class="btn btn-navy btn-sm-sies"><x-icon name="plus" :size="14" /> New request</a>
            </x-empty-state>
        @else
            <div class="table-responsive">
                <table class="table table-sies table-hover align-middle">
                    <thead>
                        <tr><th>Student</th><th>Class</th><th>Seats</th><th>Documents</th><th>Status</th><th>Requested</th><th class="text-end">Actions</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($enrollments as $enrollment)
                            @php
                                $student = $enrollment->student;
                                $class = $enrollment->classOffering;
                                $locked = $class->term->isClosed();
                                $docs = $verified[$enrollment->student_id] ?? 0;
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('students.show', $student) }}" class="fw-semibold text-decoration-none">{{ $student->student_number }}</a>
                                    <div class="small">{{ $student->full_name }}</div>
                                    <div class="text-secondary small">{{ $student->program->code }} · Year {{ $student->year_level }}</div>
                                </td>
                                <td>
                                    <a href="{{ route('classes.show', $class) }}" class="fw-semibold text-decoration-none">{{ $class->class_code }}</a>
                                    <div class="small">{{ $class->subject->code }} · {{ $class->subject->title }}</div>
                                    <div class="text-secondary small">{{ $class->scheduleSummary() }}</div>
                                </td>
                                <td class="text-nowrap">{{ $class->seats_taken }} / {{ $class->capacity }}</td>
                                <td class="text-nowrap small">
                                    @if ($requiredCount === 0)
                                        <span class="text-secondary">None required</span>
                                    @elseif ($docs >= $requiredCount)
                                        <span class="text-success">All verified</span>
                                    @else
                                        <span class="text-warning-emphasis">{{ $docs }} of {{ $requiredCount }} verified</span>
                                    @endif
                                </td>
                                <td>
                                    <x-status-badge :value="$enrollment->status" />
                                    @if ($enrollment->status_reason)
                                        <div class="text-secondary small mt-1">{{ $enrollment->status_reason }}</div>
                                    @endif
                                    @if ($enrollment->reviewer && in_array($enrollment->status, ['approved', 'rejected', 'cancelled', 'enrolled']))
                                        <div class="text-secondary small">by {{ $enrollment->reviewer->name }}</div>
                                    @endif
                                </td>
                                <td class="small text-nowrap">
                                    {{ $enrollment->requested_at?->format('M j, Y') }}
                                    <div class="text-secondary">{{ $enrollment->requester?->name }}</div>
                                </td>
                                <td class="text-end text-nowrap">
                                    @if ($locked)
                                        <span class="text-secondary small">Term closed</span>
                                    @else
                                        @if ($enrollment->status === 'pending')
                                            <button type="button" class="btn btn-outline-success btn-sm-sies"
                                                data-bs-toggle="modal" data-bs-target="#confirmModal"
                                                data-confirm-title="Approve this request?"
                                                data-confirm-message="{{ $student->full_name }} will hold a seat in {{ $class->class_code }}. Documents, schedule, seats and the unit limit are checked."
                                                data-confirm-action="{{ route('enrollments.approve', $enrollment) }}"
                                                data-confirm-button="Approve">Approve</button>
                                            <button type="button" class="btn btn-outline-danger btn-sm-sies"
                                                data-bs-toggle="modal" data-bs-target="#reasonModal"
                                                data-reason-title="Reject request"
                                                data-reason-message="{{ $student->full_name }} · {{ $class->class_code }}"
                                                data-reason-action="{{ route('enrollments.reject', $enrollment) }}"
                                                data-reason-button="Reject">Reject</button>
                                        @elseif ($enrollment->status === 'approved')
                                            <button type="button" class="btn btn-navy btn-sm-sies"
                                                data-bs-toggle="modal" data-bs-target="#confirmModal"
                                                data-confirm-title="Enroll this student?"
                                                data-confirm-message="{{ $student->full_name }} will appear on the {{ $class->class_code }} roster and attendance sheet."
                                                data-confirm-action="{{ route('enrollments.enroll', $enrollment) }}"
                                                data-confirm-button="Enroll">Enroll</button>
                                        @elseif (in_array($enrollment->status, ['rejected', 'cancelled']))
                                            <button type="button" class="btn btn-outline-secondary btn-sm-sies"
                                                data-bs-toggle="modal" data-bs-target="#confirmModal"
                                                data-confirm-title="Reopen this request?"
                                                data-confirm-message="It goes back to pending, if the class and the student still pass the rules."
                                                data-confirm-action="{{ route('enrollments.reopen', $enrollment) }}"
                                                data-confirm-button="Reopen">Reopen</button>
                                        @endif

                                        @if (in_array($enrollment->status, ['pending', 'approved', 'enrolled']))
                                            <button type="button" class="btn btn-outline-secondary btn-sm-sies"
                                                data-bs-toggle="modal" data-bs-target="#reasonModal"
                                                data-reason-title="Cancel request"
                                                data-reason-message="{{ $student->full_name }} · {{ $class->class_code }}. The seat is released."
                                                data-reason-action="{{ route('enrollments.cancel', $enrollment) }}"
                                                data-reason-button="Cancel request">Cancel</button>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-table-footer :items="$enrollments" noun="request" />
        @endif
    </div>

    <x-reason-modal />
</x-app-layout>
