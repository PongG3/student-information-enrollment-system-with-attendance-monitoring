<x-app-layout title="Class Management" subtitle="Subjects offered each term, with teacher, schedule and seats.">
    <x-slot:actions>
        <button type="button" class="btn btn-navy" data-bs-toggle="modal" data-bs-target="#addClassModal"><x-icon name="plus" :size="16" /> Add class</button>
    </x-slot:actions>

    <div class="card-sies">
        <div class="card-header-sies">
            <form method="GET" class="filter-bar">
                <select name="term" class="form-select" onchange="this.form.submit()">
                    @foreach ($terms as $term)
                        <option value="{{ $term->id }}" @selected((string) $termId === (string) $term->id)>
                            {{ $term->name }} · {{ $term->academicYear->name }}{{ $term->is_current ? ' (current)' : '' }}
                        </option>
                    @endforeach
                </select>
                <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search code, subject or teacher">
                <select name="status" class="form-select">
                    <option value="">All statuses</option>
                    @foreach (['open' => 'Open', 'closed' => 'Closed', 'cancelled' => 'Cancelled'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="btn btn-outline-secondary btn-sm-sies">Filter</button>
                @if (request()->hasAny(['search', 'status']))
                    <a href="{{ route('classes.index', ['term' => $termId]) }}" class="small">Clear</a>
                @endif
            </form>
        </div>

        @if ($classes->isEmpty())
            <x-empty-state icon="classes" message="No classes found for this term.">
                <button type="button" class="btn btn-navy btn-sm-sies" data-bs-toggle="modal" data-bs-target="#addClassModal"><x-icon name="plus" :size="14" /> Add class</button>
            </x-empty-state>
        @else
            <div class="table-responsive" data-fit-viewport>
                <table class="table table-sies table-hover">
                    <thead>
                        <tr><th>Class</th><th>Subject</th><th>Teacher</th><th>Schedule</th><th>Seats</th><th>Status</th><th class="text-end">Actions</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($classes as $class)
                            <tr>
                                <td class="fw-semibold">
                                    <a href="{{ route('classes.show', $class) }}" class="text-decoration-none">{{ $class->class_code }}</a>
                                    <div class="text-secondary small">{{ $class->block_label }}</div>
                                </td>
                                <td>{{ $class->subject->code }}<div class="text-secondary small">{{ $class->subject->title }}</div></td>
                                <td>{{ $class->teacher->name }}</td>
                                <td class="small">{{ $class->scheduleSummary() }}</td>
                                <td>{{ $class->seats_taken }} / {{ $class->capacity }}</td>
                                <td><x-status-badge :value="$class->status" /></td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('classes.show', $class) }}" class="btn btn-outline-secondary btn-sm-sies">View</a>
                                    @unless ($class->term->isClosed())
                                        <a href="{{ route('classes.edit', $class) }}" data-dialog data-dialog-size="xl" class="btn btn-outline-secondary btn-sm-sies">Edit</a>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-table-footer :items="$classes" noun="class" />
        @endif
    </div>

    @include('classes._add-dialog')
</x-app-layout>
