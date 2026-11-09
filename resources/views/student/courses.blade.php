<x-app-layout title="Available Courses" subtitle="Classes offered this term. To add or drop a class, please see the registrar's office.">
    <div class="card-sies">
        <div class="card-header-sies">
            <form method="GET" class="filter-bar">
                <select name="term" class="form-select" onchange="this.form.submit()" aria-label="Term">
                    @foreach ($terms as $option)
                        <option value="{{ $option->id }}" @selected($term && $term->id === $option->id)>
                            {{ $option->name }} · {{ $option->academicYear->name }}{{ $option->is_current ? ' (current)' : '' }}
                        </option>
                    @endforeach
                </select>
                <input type="search" name="q" value="{{ $search }}" class="form-control" placeholder="Search subject or class code" maxlength="60">
                <button class="btn btn-outline-secondary btn-sm-sies">Search</button>
                @if ($search !== '')<a href="{{ route('student.courses', array_filter(['term' => $term?->id])) }}" class="small">Clear</a>@endif
            </form>
            @if ($term)
                <span class="small text-secondary">Enrollment is {{ $term->enrollment_open ? 'open' : 'closed' }} for this term.</span>
            @endif
        </div>

        @if (! $term)
            <x-empty-state message="There is no open term right now." />
        @elseif ($classes->isEmpty())
            <x-empty-state message="No classes match." />
        @else
            <div class="table-responsive">
                <table class="table table-sies table-hover align-middle mb-0">
                    <thead><tr><th>Class</th><th>Subject</th><th>Units</th><th>Schedule</th><th>Teacher</th><th>Seats left</th><th>My status</th></tr></thead>
                    <tbody>
                        @foreach ($classes as $class)
                            @php $left = max(0, $class->capacity - $class->seats_taken); @endphp
                            <tr>
                                <td class="fw-semibold">{{ $class->class_code }}<div class="text-secondary small fw-normal">Section {{ $class->section }}</div></td>
                                <td>{{ $class->subject->code }}<div class="text-secondary small">{{ $class->subject->title }}</div></td>
                                <td>{{ rtrim(rtrim((string) $class->subject->units, '0'), '.') }}</td>
                                <td class="small">{{ $class->scheduleSummary() ?: '—' }}</td>
                                <td>{{ $class->teacher->name }}</td>
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
</x-app-layout>
