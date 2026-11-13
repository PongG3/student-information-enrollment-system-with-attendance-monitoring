<x-app-layout title="My Enrollments" subtitle="Every class request you have and where it stands.">
    <div class="card-sies">
        <div class="card-header-sies">
            <form method="GET" class="filter-bar">
                <select name="term" class="form-select" onchange="this.form.submit()" aria-label="Term">
                    @foreach ($terms as $option)
                        <option value="{{ $option->id }}" @selected(! $all && $term && $term->id === $option->id)>
                            {{ $option->name }} · {{ $option->academicYear->name }}{{ $option->is_current ? ' (current)' : '' }}
                        </option>
                    @endforeach
                    <option value="all" @selected($all)>All terms</option>
                </select>
            </form>
            <span class="small text-secondary">Units enrolled: <strong>{{ \App\Services\StudentPortalData::number($enrolledUnits) }}</strong></span>
        </div>

        @if ($enrollments->isEmpty())
            <x-empty-state icon="clipboard" message="No class requests for this selection." />
        @else
            <div class="table-responsive" data-fit-viewport>
                <table class="table table-sies align-middle mb-0">
                    <thead><tr><th scope="col">Class</th><th scope="col">Subject</th><th scope="col">Units</th><th scope="col">Schedule</th><th scope="col">Term</th><th scope="col">Status</th></tr></thead>
                    <tbody>
                        @foreach ($enrollments as $enrollment)
                            @php $class = $enrollment->classOffering; @endphp
                            <tr>
                                <td class="fw-semibold">{{ $class->class_code }}<div class="text-secondary small fw-normal">{{ $class->block_label }}</div></td>
                                <td>{{ $class->subject->code }}<div class="text-secondary small">{{ $class->subject->title }}</div></td>
                                <td>{{ rtrim(rtrim((string) $class->subject->units, '0'), '.') }}</td>
                                <td class="small">{{ $class->scheduleSummary() ?: '—' }}</td>
                                <td class="small">{{ $class->term->name }} · {{ $class->term->academicYear->name }}</td>
                                <td>
                                    <x-status-badge :value="$enrollment->status" />
                                    @if ($enrollment->status_reason)<div class="text-secondary small">{{ $enrollment->status_reason }}</div>@endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-3 small text-secondary">
                Pending: waiting for review · Approved: seat held, enrollment to be confirmed · Enrolled: you are officially in the class.
            </div>
        @endif
    </div>
</x-app-layout>
