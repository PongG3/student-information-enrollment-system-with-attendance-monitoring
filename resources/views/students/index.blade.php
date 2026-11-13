@php
    $filtered = request()->hasAny(['search', 'program', 'year_level', 'section', 'status', 'type', 'archived']);
@endphp
<x-app-layout title="Student Records" subtitle="Register students, keep their records, and archive instead of deleting.">
    <x-slot:actions>
        <a href="{{ route('students.import') }}" class="btn btn-outline-secondary"><x-icon name="file" :size="16" /> Import CSV</a>
        <a href="{{ route('students.export', request()->query()) }}" class="btn btn-outline-secondary"><x-icon name="file" :size="16" /> Export CSV</a>
        <a href="{{ route('students.create') }}" data-dialog data-dialog-size="xl" class="btn btn-navy"><x-icon name="plus" :size="16" /> Add student</a>
    </x-slot:actions>

    <div class="card-sies">
        <div class="card-header-sies">
            <form method="GET" class="filter-bar" role="search" aria-label="Filter students">
                <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search name, ID or email" aria-label="Search name, ID or email">
                <select name="program" class="form-select" aria-label="Program">
                    <option value="">All programs</option>
                    @foreach ($programs as $program)
                        <option value="{{ $program->id }}" @selected((string) request('program') === (string) $program->id)>{{ $program->code }}</option>
                    @endforeach
                </select>
                <select name="year_level" class="form-select" aria-label="Year level">
                    <option value="">All year levels</option>
                    @for ($level = 1; $level <= 6; $level++)
                        <option value="{{ $level }}" @selected((string) request('year_level') === (string) $level)>Year {{ $level }}</option>
                    @endfor
                </select>
                @if ($sections->isNotEmpty())
                    <select name="section" class="form-select" aria-label="Section">
                        <option value="">All sections</option>
                        @foreach ($sections as $section)
                            <option value="{{ $section }}" @selected(request('section') === $section)>Section {{ $section }}</option>
                        @endforeach
                    </select>
                @endif
                <select name="status" class="form-select" aria-label="Status">
                    <option value="">All statuses</option>
                    @foreach (['active' => 'Active', 'inactive' => 'Inactive', 'graduated' => 'Graduated', 'withdrawn' => 'Withdrawn'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="type" class="form-select" aria-label="Student type">
                    <option value="">All types</option>
                    @foreach (['new' => 'New', 'returning' => 'Returning', 'transferee' => 'Transferee'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <div class="form-check ms-1">
                    <input class="form-check-input" type="checkbox" name="archived" value="1" id="archived" @checked($archived)>
                    <label class="form-check-label small" for="archived">Archived only</label>
                </div>
                <button class="btn btn-outline-secondary btn-sm-sies">Filter</button>
                @if ($filtered)
                    <a href="{{ route('students.index') }}" class="small">Clear filters</a>
                @endif
            </form>
        </div>

        @if ($students->isEmpty())
            @if ($filtered)
                <x-empty-state :message="$archived ? 'No archived students.' : 'No students match your filters.'">
                    <a href="{{ route('students.index') }}" class="btn btn-outline-secondary btn-sm-sies">Clear filters</a>
                </x-empty-state>
            @else
                <x-empty-state icon="students" message="No students are registered yet.">
                    <a href="{{ route('students.create') }}" data-dialog data-dialog-size="xl" class="btn btn-navy btn-sm-sies"><x-icon name="plus" :size="14" /> Add the first student</a>
                </x-empty-state>
            @endif
        @else
            <div class="table-responsive" data-fit-viewport>
                <table class="table table-sies table-hover table-stack">
                    <thead><tr><th scope="col">Student</th><th scope="col">Program</th><th scope="col">Year</th><th scope="col">Section</th><th scope="col">Type</th><th scope="col">Status</th><th scope="col" class="text-end">Actions</th></tr></thead>
                    <tbody>
                        @foreach ($students as $student)
                            <tr>
                                <td>
                                    <a href="{{ route('students.show', $student) }}" class="fw-semibold text-navy">{{ $student->full_name }}</a>
                                    <div class="small text-secondary">{{ $student->student_number }}</div>
                                </td>
                                <td data-label="Program">{{ $student->program->code }}</td>
                                <td data-label="Year">{{ $student->year_level }}</td>
                                <td data-label="Section">{{ $student->section ?: '—' }}</td>
                                <td data-label="Type">{{ ucfirst($student->student_type) }}</td>
                                <td data-label="Status">
                                    @if ($student->trashed()) <x-status-badge value="inactive" label="Archived" />
                                    @else <x-status-badge :value="$student->status" />
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('students.show', $student) }}" class="btn btn-outline-secondary btn-sm-sies">View</a>
                                    @if ($student->trashed())
                                        <button type="button" class="btn btn-outline-success btn-sm-sies"
                                            data-bs-toggle="modal" data-bs-target="#confirmModal"
                                            data-confirm-title="Restore student?"
                                            data-confirm-message="{{ $student->full_name }} will appear in the student list again."
                                            data-confirm-action="{{ route('students.restore', $student) }}"
                                            data-confirm-button="Restore">Restore</button>
                                    @else
                                        <a href="{{ route('students.edit', $student) }}" data-dialog data-dialog-size="xl" class="btn btn-outline-secondary btn-sm-sies">Edit</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-table-footer :items="$students" noun="student" />
        @endif
    </div>
</x-app-layout>
