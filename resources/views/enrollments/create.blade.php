<x-app-layout title="New Enrollment Request" subtitle="Choose the student, then the classes they want. Each class is checked before it is saved."
    :back="route('enrollments.index')" back-label="Back to requests">
    @if (! $term)
        <div class="card-sies p-4">
            <div class="alert alert-warning mb-3">Enrollment is closed. No new requests can be recorded right now.</div>
            <p class="text-secondary small mb-3">An administrator can open it on <strong>Academic Years &amp; Terms</strong> by making a term current and choosing Open enrollment.</p>
            <a href="{{ route('enrollments.index') }}" class="btn btn-outline-secondary btn-sm-sies">Back to requests</a>
        </div>
    @elseif (! $student)
        @include('enrollments._steps', ['step' => 1])
        <div class="card-sies p-4 card-narrow">
            <h2 class="section-title mb-1">Find the student</h2>
            <p class="text-secondary small">Enrolling for {{ $term->name }} · {{ $term->academicYear->name }}. Only active students appear.</p>

            <form method="GET" class="filter-bar mb-3">
                <input type="search" name="q" value="{{ request('q') }}" class="form-control flex-grow-1" placeholder="Student ID or name" aria-label="Student ID or name" autofocus>
                <button class="btn btn-navy btn-sm-sies px-3">Search</button>
            </form>

            @if (request()->filled('q'))
                @if ($results->isEmpty())
                    <x-empty-state icon="students" message="No active student matches that search." />
                @else
                    <div class="table-responsive">
                        <table class="table table-sies align-middle">
                            <thead><tr><th>Student ID</th><th>Name</th><th>Program</th><th></th></tr></thead>
                            <tbody>
                                @foreach ($results as $result)
                                    <tr>
                                        <td class="fw-semibold">{{ $result->student_number }}</td>
                                        <td>{{ $result->full_name }}</td>
                                        <td>{{ $result->program->code }} · Year {{ $result->year_level }}</td>
                                        <td class="text-end"><a href="{{ route('enrollments.create', ['student' => $result->id]) }}" class="btn btn-outline-secondary btn-sm-sies">Choose</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif
        </div>
    @else
        @if ($errors->has('enrollment'))
            <div class="alert alert-danger">
                <div class="fw-semibold mb-1">Nothing was saved:</div>
                <ul class="mb-0 ps-3">
                    @foreach ($errors->get('enrollment') as $message)<li>{{ $message }}</li>@endforeach
                </ul>
            </div>
        @endif
        @foreach (['class_ids', 'student_id'] as $field)
            @error($field)<div class="alert alert-danger">{{ $message }}</div>@enderror
        @endforeach

        @include('enrollments._steps', ['step' => 2])
        {{-- One slim strip: who is being enrolled, for which term, and how many units they already hold. --}}
        <div class="card-sies student-strip mb-3">
            <span class="avatar" aria-hidden="true">{{ strtoupper(substr($student->first_name, 0, 1) . substr($student->last_name, 0, 1)) }}</span>
            <div class="min-w-0">
                <div class="fw-bold text-navy">{{ $student->full_name }}</div>
                <div class="text-secondary small">{{ $student->student_number }} · {{ $student->block_section ?? ($student->program->code . ' · Year ' . $student->year_level) }}</div>
            </div>
            <div class="small student-strip-term">
                <div>{{ $term->name }} · {{ $term->academicYear->name }}</div>
                <div class="text-secondary">Units held: {{ rtrim(rtrim(number_format($units, 1), '0'), '.') }} of {{ rtrim(rtrim(number_format($maxUnits, 1), '0'), '.') }}</div>
            </div>
            <a href="{{ route('enrollments.create') }}" class="btn btn-outline-secondary btn-sm-sies">Change student</a>
        </div>

        <form method="POST" action="{{ route('enrollments.store') }}">
            @csrf
            <input type="hidden" name="student_id" value="{{ $student->id }}">

            <div class="card-sies">
                <div class="card-header-sies">
                    <h2 class="h6 fw-bold mb-0">Choose classes</h2>
                    <span class="text-secondary small">Open classes this term</span>
                </div>

                @if ($rows->isEmpty())
                    <x-empty-state icon="classes" message="No open classes found." />
                @else
                    <div class="table-responsive" data-fit-viewport>
                        <table class="table table-sies align-middle">
                            <thead><tr><th class="col-check"><span class="visually-hidden">Choose</span></th><th>Class</th><th>Teacher</th><th>Schedule</th><th>Units</th><th>Seats left</th></tr></thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    @php
                                        $class = $row['class'];
                                        $blocked = $row['existing'] || count($row['problems']) > 0;
                                    @endphp
                                    <tr class="{{ $blocked ? 'text-secondary' : '' }}">
                                        <td>
                                            @unless ($blocked)
                                                <input class="form-check-input" type="checkbox" name="class_ids[]" value="{{ $class->id }}"
                                                    id="class{{ $class->id }}" data-units="{{ (float) $class->subject->units }}" @checked(is_array(old('class_ids')) && in_array($class->id, old('class_ids')))
                                                    aria-label="Choose {{ $class->class_code }}">
                                            @endunless
                                        </td>
                                        <td>
                                            <label for="class{{ $class->id }}" class="fw-semibold">{{ $class->class_code }}</label> <span class="small text-secondary">{{ $class->block_label }}</span>
                                            <div class="small">{{ $class->subject->code }} · {{ $class->subject->title }}</div>
                                            @if ($row['existing'])
                                                <div class="small mt-1">Already requested: <x-status-badge :value="$row['existing']->status" /></div>
                                            @endif
                                            @foreach ($row['problems'] as $problem)
                                                <div class="small text-danger">{{ $problem }}</div>
                                            @endforeach
                                        </td>
                                        <td class="small">{{ $class->teacher->name }}</td>
                                        <td class="small">{{ $class->scheduleSummary() }}</td>
                                        <td>{{ rtrim(rtrim(number_format((float) $class->subject->units, 1), '0'), '.') }}</td>
                                        <td>{{ max(0, $class->capacity - $class->seats_taken) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Always in view, however long the class list: what is chosen so far, and the action. --}}
            <x-form-actions sticky class="mt-3" submit="Record as pending" :cancel="route('enrollments.index')">
                <x-slot:lead>
                    <span class="small" data-selection-summary data-held="{{ (float) $units }}" data-max="{{ (float) $maxUnits }}" aria-live="polite">
                        <strong data-selected-count>0</strong> classes chosen ·
                        <strong data-selected-units>0</strong> units
                        <span class="text-secondary">(term total <span data-total-units>{{ rtrim(rtrim(number_format($units, 1), '0'), '.') }}</span> of {{ rtrim(rtrim(number_format($maxUnits, 1), '0'), '.') }})</span>
                    </span>
                </x-slot:lead>
            </x-form-actions>
        </form>
    @endif
</x-app-layout>
