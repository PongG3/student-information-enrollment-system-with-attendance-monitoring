<x-app-layout title="Available Courses" subtitle="Classes of your program and general subjects offered this term. To add or drop a class, please see the registrar's office.">
    <div class="card-sies">
        <div class="card-header-sies">
            {{-- Live: results update as you type (after a short pause) and when the term changes. --}}
            <form method="GET" action="{{ route('student.courses') }}" class="filter-bar" role="search" aria-label="Search classes" data-live-filter>
                <input type="search" name="q" value="{{ $search }}" class="form-control" placeholder="Search subject or schedule code" aria-label="Search subject or schedule code" maxlength="60" autocomplete="off">
                <select name="term" class="form-select" aria-label="Term" data-live-keep>
                    @foreach ($terms as $option)
                        <option value="{{ $option->id }}" @selected($term && $term->id === $option->id)>
                            {{ $option->name }} · {{ $option->academicYear->name }}{{ $option->is_current ? ' (current)' : '' }}
                        </option>
                    @endforeach
                </select>
                <noscript><button class="btn btn-outline-secondary btn-sm-sies">Search</button></noscript>
                <span data-live-region="clear">@if ($search !== '')<a href="{{ route('student.courses', array_filter(['term' => $term?->id])) }}" class="btn btn-link btn-sm p-0 small" data-live-clear>Clear</a>@endif</span>
            </form>
            <span data-live-region="term-note">@if ($term)
                <span class="small text-secondary">Enrollment is {{ $term->enrollment_open ? 'open' : 'closed' }} for this term.</span>
            @endif</span>
        </div>

        <div class="live-results" data-live-region="results">
        @if (! $term)
            <x-empty-state icon="calendar" message="There is no open term right now." />
        @elseif ($classes->isEmpty())
            <x-empty-state icon="book" message="No classes match." />
        @else
            <div class="table-responsive" data-fit-viewport>
                <table class="table table-sies table-hover align-middle mb-0">
                    <thead><tr><th scope="col">Class</th><th scope="col">Subject</th><th scope="col">Units</th><th scope="col">Schedule</th><th scope="col">Teacher</th><th scope="col">Seats left</th><th scope="col">My status</th></tr></thead>
                    <tbody>
                        @foreach ($classes as $class)
                            @php $left = max(0, $class->capacity - $class->seats_taken); @endphp
                            <tr>
                                <td class="fw-semibold">{{ $class->class_code }}<div class="text-secondary small fw-normal">{{ $class->block_label }}</div></td>
                                <td>{{ $class->subject->code }}<div class="text-secondary small">{{ $class->subject->title }}</div>@if ($class->subject->prerequisites->isNotEmpty())<div class="small text-secondary">Needs {{ $class->subject->prerequisites->pluck('code')->implode(', ') }}</div>@endif</td>
                                <td>{{ rtrim(rtrim((string) $class->subject->units, '0'), '.') }}</td>
                                <td class="small">{{ $class->scheduleSummary() ?: '—' }}</td>
                                <td><x-teacher-chip :teacher="$class->teacher" /></td>
                                <td>
                                    @if ($class->status !== 'open') <x-status-badge :value="$class->status" />
                                    @elseif ($left === 0) <span class="text-danger fw-semibold">Full</span>
                                    @else {{ $left }} of {{ $class->capacity }}
                                    @endif
                                </td>
                                <td>
                                    @if (isset($mine[$class->id])) <x-status-badge :value="$mine[$class->id]" />
                                    @else <span class="text-secondary">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        </div>
    </div>
</x-app-layout>
