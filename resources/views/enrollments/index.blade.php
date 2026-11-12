{{-- Enrollment requests, one block per student: the student once, then one short line per class.
     Opens on Pending while anything waits. Bulk actions select whole students. --}}
<x-app-layout title="Enrollment Requests" subtitle="Record requests for students, then approve, enroll, reject or cancel them.">
    <x-slot:actions>
        <a href="{{ route('enrollments.create') }}" data-dialog data-dialog-size="lg" class="btn btn-navy"><x-icon name="plus" :size="16" /> New request</a>
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

    @php
        // Filters kept when switching status tabs.
        $keep = array_filter(['term' => $termId, 'program' => request('program'), 'docs' => request('docs'), 'search' => request('search')]);
        $filtering = request()->filled('search') || request()->filled('program') || request('docs') === 'incomplete';
    @endphp

    <div class="card-sies">
        {{-- One toolbar: status, then term, program, documents and search. --}}
        <div class="card-header-sies request-toolbar">
            <ul class="status-tabs" aria-label="Filter by status">
                @foreach (['all' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'enrolled' => 'Enrolled', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'] as $value => $label)
                    <li class="nav-item">
                        <a class="nav-link {{ $status === $value ? 'active' : '' }}" @if ($status === $value) aria-current="page" @endif
                           href="{{ route('enrollments.index', $keep + ['status' => $value]) }}">
                            {{ $label }} <span class="status-tab-count">{{ $value === 'all' ? array_sum($counts) : ($counts[$value] ?? 0) }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
            <form method="GET" class="filter-bar">
                <input type="hidden" name="status" value="{{ $status }}">
                <select name="term" class="form-select" aria-label="Term" onchange="this.form.submit()">
                    @foreach ($terms as $term)
                        <option value="{{ $term->id }}" @selected((string) $termId === (string) $term->id)>
                            {{ $term->name }} · {{ $term->academicYear->name }}{{ $term->is_current ? ' (current)' : '' }}
                        </option>
                    @endforeach
                </select>
                <select name="program" class="form-select" aria-label="Program" onchange="this.form.submit()">
                    <option value="">All programs</option>
                    @foreach ($programs as $id => $code)
                        <option value="{{ $id }}" @selected((string) request('program') === (string) $id)>{{ $code }}</option>
                    @endforeach
                </select>
                @if ($requiredCount > 0)
                    <input type="checkbox" class="btn-check" name="docs" value="incomplete" id="docsIncomplete" onchange="this.form.submit()" @checked(request('docs') === 'incomplete')>
                    <label class="btn btn-outline-warning btn-sm-sies text-nowrap" for="docsIncomplete">Documents incomplete</label>
                @endif
                <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Student, class or subject" aria-label="Search student, class or subject">
                <button class="btn btn-outline-secondary btn-sm-sies">Search</button>
                @if ($filtering)
                    <a href="{{ route('enrollments.index', ['term' => $termId, 'status' => $status]) }}" class="small text-nowrap">Clear filters</a>
                @endif
            </form>
        </div>

        @if ($groups->isEmpty())
            <x-empty-state icon="clipboard" :message="$filtering ? 'No enrollment requests match.' : ($status === 'pending' ? 'No requests are waiting. Everything is reviewed.' : 'No enrollment requests here.')">
                <a href="{{ route('enrollments.create') }}" data-dialog data-dialog-size="lg" class="btn btn-navy btn-sm-sies"><x-icon name="plus" :size="14" /> New request</a>
            </x-empty-state>
        @else
            @php
                // What each student on the page still needs: pending to approve, approved to enroll.
                $todo = $groups->mapWithKeys(fn ($g) => [$g['student']->id => [
                    'pending' => $g['requests']->filter(fn ($e) => $e->status === 'pending' && ! $e->classOffering->term->isClosed())->count(),
                    'approved' => $g['requests']->filter(fn ($e) => $e->status === 'approved' && ! $e->classOffering->term->isClosed())->count(),
                ]]);
                $selectable = $todo->contains(fn ($t) => $t['pending'] + $t['approved'] > 0);
            @endphp
            @if ($selectable)
                {{-- Bulk: tick students, then approve their pending requests or enroll their approved ones. Each request is still checked on its own. --}}
                <form method="POST" action="{{ route('enrollments.bulk') }}" id="bulkForm" class="bulk-bar" data-bulk-form>
                    @csrf
                    <input type="hidden" name="term" value="{{ $termId }}">
                    <input type="checkbox" class="form-check-input" data-bulk-all id="bulkAll" aria-label="Select every student on this page">
                    <label for="bulkAll" class="small text-secondary mb-0" data-bulk-count>Tick students to approve or enroll several at once.</label>
                    <div class="d-flex gap-2 ms-auto">
                        <button type="submit" name="action" value="approve" class="btn btn-outline-success btn-sm-sies" disabled data-bulk-button>Approve selected</button>
                        <button type="submit" name="action" value="enroll" class="btn btn-navy btn-sm-sies" disabled data-bulk-button>Enroll selected</button>
                    </div>
                </form>
            @endif

            <div class="table-responsive" data-fit-viewport>
                <table class="table table-sies request-groups align-middle mb-0">
                    <thead>
                        <tr><th>Class</th><th>Schedule</th><th>Seats</th><th>Status</th><th>Requested</th><th class="text-end">Actions</th></tr>
                    </thead>
                    @foreach ($groups as $group)
                        @php
                            $student = $group['student'];
                            $docs = $verified[$student->id] ?? 0;
                            $left = $todo[$student->id];
                            $overUnits = $group['units'] > $maxUnits;
                        @endphp
                        @php
                            $unitText = fn ($n) => rtrim(rtrim(number_format($n, 1), '0'), '.');
                            $unitPct = $maxUnits > 0 ? min(100, (int) round($group['units'] / $maxUnits * 100)) : 0;
                            $rowsId = 'req-' . $student->id;
                        @endphp
                        <tbody class="request-group" id="{{ $rowsId }}">
                            <tr class="request-student">
                                <td colspan="6">
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        {{-- Collapse a student's classes to see more students at once. --}}
                                        <button type="button" class="request-toggle" data-request-toggle="{{ $rowsId }}" aria-expanded="true"
                                                aria-label="Show or hide the classes of {{ $student->full_name }}">
                                            <x-icon name="chevron" :size="16" />
                                        </button>
                                        @if ($left['pending'] + $left['approved'] > 0)
                                            <input type="checkbox" class="form-check-input mt-0" name="students[]" value="{{ $student->id }}" form="bulkForm"
                                                   data-bulk-item data-bulk-pending="{{ $left['pending'] }}" data-bulk-approved="{{ $left['approved'] }}"
                                                   aria-label="Select {{ $student->full_name }}">
                                        @endif
                                        <a href="{{ route('students.show', $student) }}" class="fw-bold text-navy text-decoration-none">{{ $student->full_name }}</a>
                                        <span class="text-secondary small">{{ $student->student_number }} · {{ $student->program->code }} {{ $student->year_level }}{{ $student->section ? '-' . $student->section : '' }}</span>
                                        <span class="request-count small text-secondary">{{ $group['requests']->count() }} {{ \Illuminate\Support\Str::plural('class', $group['requests']->count()) }}</span>

                                        @if ($requiredCount === 0)
                                            <x-status-badge value="inactive" label="No documents required" />
                                        @elseif ($docs >= $requiredCount)
                                            <x-status-badge value="verified" label="Documents verified" />
                                        @else
                                            <x-status-badge value="pending" :label="$docs . ' of ' . $requiredCount . ' documents'" />
                                        @endif

                                        <span class="unit-meter small" title="Pending, approved and enrolled classes this term">
                                            <span class="unit-meter-bar" role="progressbar" aria-label="Units" aria-valuenow="{{ $unitText($group['units']) }}" aria-valuemin="0" aria-valuemax="{{ $unitText($maxUnits) }}">
                                                <span @class(['unit-meter-fill', 'is-over' => $overUnits]) style="width: {{ $unitPct }}%"></span>
                                            </span>
                                            <span @class(['text-danger fw-semibold' => $overUnits, 'text-secondary' => ! $overUnits])>{{ $unitText($group['units']) }} of {{ $unitText($maxUnits) }} units</span>
                                        </span>

                                        <span class="ms-auto d-flex gap-2">
                                            @foreach ([['approve', $left['pending'], 'Approve all', 'btn-outline-success'], ['enroll', $left['approved'], 'Enroll all', 'btn-navy']] as [$action, $count, $verb, $style])
                                                @if ($count > 1)
                                                    <button type="button" class="btn {{ $style }} btn-sm-sies"
                                                        data-bs-toggle="modal" data-bs-target="#confirmModal"
                                                        data-confirm-title="{{ $verb }} {{ $count }} requests?"
                                                        data-confirm-message="{{ $student->full_name }}: each class is checked on its own (schedule, seats, documents and the unit limit)."
                                                        data-confirm-action="{{ route('enrollments.bulk', ['action' => $action, 'students' => [$student->id], 'term' => $termId]) }}"
                                                        data-confirm-button="{{ $verb }}">{{ $verb }} {{ $count }}</button>
                                                @endif
                                            @endforeach
                                        </span>
                                    </div>
                                </td>
                            </tr>
                            @foreach ($group['requests'] as $enrollment)
                                @php
                                    $class = $enrollment->classOffering;
                                    $locked = $class->term->isClosed();
                                @endphp
                                <tr class="request-line">
                                    <td>
                                        <a href="{{ route('classes.show', $class) }}" class="fw-semibold text-decoration-none">{{ $class->subject->code }}</a>
                                        <span class="text-secondary small">· {{ $class->subject->title }} · {{ $class->class_code }}</span>
                                    </td>
                                    <td class="small text-secondary">{{ $class->scheduleSummary() }}</td>
                                    <td class="text-nowrap small">{{ $class->seats_taken }} / {{ $class->capacity }}</td>
                                    <td>
                                        <x-status-badge :value="$enrollment->status" />
                                        @if ($enrollment->status_reason)
                                            <div class="text-secondary small mt-1">{{ $enrollment->status_reason }}</div>
                                        @endif
                                    </td>
                                    <td class="small text-nowrap text-secondary"
                                        title="Requested by {{ $enrollment->requester?->name ?? 'unknown' }}@if ($enrollment->reviewer && $enrollment->status !== 'pending') · {{ ucfirst($enrollment->status) }} by {{ $enrollment->reviewer->name }}@endif">
                                        {{ $enrollment->requested_at?->format('M j') }}
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

                                            {{-- Undoing: "Drop" takes an enrolled student off the class; "Cancel" withdraws a request.
                                                 Quiet style (it is not the main action), and always asks for a reason first. --}}
                                            @if ($enrollment->status === 'enrolled')
                                                <button type="button" class="btn btn-quiet-danger btn-sm-sies"
                                                    data-bs-toggle="modal" data-bs-target="#reasonModal"
                                                    data-reason-title="Drop this class?"
                                                    data-reason-message="{{ $student->full_name }} leaves {{ $class->subject->code }} ({{ $class->class_code }}): off the roster and attendance sheet, and the seat is released. Recorded attendance is kept."
                                                    data-reason-action="{{ route('enrollments.cancel', $enrollment) }}"
                                                    data-reason-button="Drop class" title="Drop this enrolled class (asks for a reason)">Drop</button>
                                            @elseif (in_array($enrollment->status, ['pending', 'approved']))
                                                <button type="button" class="btn btn-quiet-danger btn-sm-sies"
                                                    data-bs-toggle="modal" data-bs-target="#reasonModal"
                                                    data-reason-title="Cancel this request?"
                                                    data-reason-message="{{ $student->full_name }} · {{ $class->subject->code }} ({{ $class->class_code }}).{{ $enrollment->status === 'approved' ? ' The reserved seat is released.' : '' }}"
                                                    data-reason-action="{{ route('enrollments.cancel', $enrollment) }}"
                                                    data-reason-button="Cancel request" title="Withdraw this request (asks for a reason)">Cancel</button>
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    @endforeach
                </table>
            </div>
            <div class="d-flex align-items-center">
                <button type="button" class="btn btn-link btn-sm small ms-2" data-request-toggle-all="collapse">Collapse all</button>
                <div class="flex-grow-1"><x-table-footer :items="$students" noun="student" /></div>
            </div>
        @endif
    </div>

    <x-reason-modal />
</x-app-layout>
