<x-app-layout title="Student Records" subtitle="Register students, keep their records, and archive instead of deleting.">
    <div class="card-sies">
        <div class="card-header-sies">
            <form method="GET" class="filter-bar">
                <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search name, ID or email">
                <select name="program" class="form-select">
                    <option value="">All programs</option>
                    @foreach ($programs as $program)
                        <option value="{{ $program->id }}" @selected((string) request('program') === (string) $program->id)>{{ $program->code }}</option>
                    @endforeach
                </select>
                <select name="year_level" class="form-select">
                    <option value="">All year levels</option>
                    @for ($level = 1; $level <= 6; $level++)
                        <option value="{{ $level }}" @selected((string) request('year_level') === (string) $level)>Year {{ $level }}</option>
                    @endfor
                </select>
                @if ($sections->isNotEmpty())
                    <select name="section" class="form-select">
                        <option value="">All sections</option>
                        @foreach ($sections as $section)
                            <option value="{{ $section }}" @selected(request('section') === $section)>Section {{ $section }}</option>
                        @endforeach
                    </select>
                @endif
                <select name="status" class="form-select">
                    <option value="">All statuses</option>
                    @foreach (['active' => 'Active', 'inactive' => 'Inactive', 'graduated' => 'Graduated', 'withdrawn' => 'Withdrawn'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="type" class="form-select">
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
                @if (request()->hasAny(['search', 'program', 'year_level', 'section', 'status', 'type', 'archived']))
                    <a href="{{ route('students.index') }}" class="small">Clear</a>
                @endif
            </form>
            <div class="d-flex gap-2">
                <a href="{{ route('students.export', request()->query()) }}" class="btn btn-outline-secondary btn-sm-sies px-3">Export CSV</a>
                <a href="{{ route('students.create') }}" class="btn btn-navy btn-sm-sies px-3">Add student</a>
            </div>
        </div>

        @if ($students->isEmpty())
            <x-empty-state :message="$archived ? 'No archived students.' : 'No students match your filters.'" />
        @else
            <div class="table-responsive">
                <table class="table table-sies table-hover">
                    <thead><tr><th>Student ID</th><th>Name</th><th>Program</th><th>Year</th><th>Section</th><th>Type</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                        @foreach ($students as $student)
                            <tr>
                                <td class="fw-semibold"><a href="{{ route('students.show', $student) }}" class="text-decoration-none">{{ $student->student_number }}</a></td>
                                <td>{{ $student->full_name }}</td>
                                <td>{{ $student->program->code }}</td>
                                <td>{{ $student->year_level }}</td>
                                <td>{{ $student->section ?: '—' }}</td>
                                <td>{{ ucfirst($student->student_type) }}</td>
                                <td>
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
                                        <a href="{{ route('students.edit', $student) }}" class="btn btn-outline-secondary btn-sm-sies">Edit</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-3">{{ $students->links() }}</div>
        @endif
    </div>
</x-app-layout>
