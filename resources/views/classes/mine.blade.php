<x-app-layout title="My Classes" subtitle="Classes assigned to you. Open a class to see its enrolled students.">
    <div class="card-sies">
        <div class="card-header-sies">
            {{-- Live: choosing a term updates the list in place. --}}
            <form method="GET" action="{{ route('teacher.classes') }}" class="filter-bar" aria-label="Choose a term" data-live-filter>
                <select name="term" class="form-select" aria-label="Term">
                    @foreach ($terms as $term)
                        <option value="{{ $term->id }}" @selected((string) $termId === (string) $term->id)>
                            {{ $term->name }} · {{ $term->academicYear->name }}{{ $term->is_current ? ' (current)' : '' }}
                        </option>
                    @endforeach
                </select>
                <noscript><button class="btn btn-outline-secondary btn-sm-sies">Show</button></noscript>
            </form>
        </div>

        <div class="live-results" data-live-region="results">
        @if ($classes->isEmpty())
            <x-empty-state icon="classes" message="You have no classes in this term." />
        @else
            <div class="table-responsive" data-fit-viewport>
                <table class="table table-sies table-hover">
                    <thead><tr><th scope="col">Class</th><th scope="col">Subject</th><th scope="col">Schedule</th><th scope="col">Enrolled</th><th scope="col">Status</th><th scope="col" class="text-end">Actions</th></tr></thead>
                    <tbody>
                        @foreach ($classes as $class)
                            <tr>
                                <td class="fw-semibold">{{ $class->class_code }}<div class="text-secondary small">{{ $class->block_label }}</div></td>
                                <td>{{ $class->subject->code }}<div class="text-secondary small">{{ $class->subject->title }}</div></td>
                                <td class="small">{{ $class->scheduleSummary() }}</td>
                                <td>{{ $class->enrolled_count }} / {{ $class->capacity }}</td>
                                <td><x-status-badge :value="$class->status" /></td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('attendance.sheet', ['class_offering' => $class->id]) }}" class="btn btn-navy btn-sm-sies">Take attendance</a>
                                    <a href="{{ route('attendance.summary', $class) }}" class="btn btn-outline-secondary btn-sm-sies">Summary</a>
                                    <a href="{{ route('classes.show', $class) }}" class="btn btn-outline-secondary btn-sm-sies">Roster</a>
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
