<x-app-layout title="Class Management" subtitle="Subjects offered each term, with teacher, schedule and seats.">
    @php
        $targetTerm = $terms->firstWhere('id', (int) $termId);
        $canCopy = $targetTerm && ! $targetTerm->isClosed() && $terms->count() > 1;
    @endphp
    <x-slot:actions>
        @if ($canCopy)
            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#copyClassesModal"><x-icon name="layers" :size="16" /> Copy from another term</button>
        @endif
        <button type="button" class="btn btn-navy" data-bs-toggle="modal" data-bs-target="#addClassModal"><x-icon name="plus" :size="16" /> Add class</button>
    </x-slot:actions>

    @if (session('copy_skipped'))
        <div class="alert alert-warning small">
            <div class="fw-semibold mb-1">{{ count(session('copy_skipped')) }} {{ \Illuminate\Support\Str::plural('class', count(session('copy_skipped'))) }} not copied:</div>
            <ul class="mb-0 ps-3">
                @foreach (session('copy_skipped') as $reason)<li>{{ $reason }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="card-sies">
        <div class="card-header-sies">
            {{-- Live: results update as you type (after a short pause) and when any filter changes. --}}
            <form method="GET" action="{{ route('classes.index') }}" class="filter-bar" role="search" aria-label="Filter classes" data-live-filter>
                <select name="term" class="form-select" aria-label="Term" data-live-keep data-live-reload>
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
                <noscript><button class="btn btn-outline-secondary btn-sm-sies">Filter</button></noscript>
                <span data-live-region="clear">@if (request()->hasAny(['search', 'status'])) <a href="{{ route('classes.index', ['term' => $termId]) }}" class="btn btn-link btn-sm p-0 small" data-live-clear>Clear</a> @endif</span>
            </form>
        </div>

        <span class="visually-hidden" data-live-region="status" aria-live="polite">{{ number_format($classes->total()) }} {{ \Illuminate\Support\Str::plural('class', $classes->total()) }} found</span>
        <div class="live-results" data-live-region="results">
        @if ($classes->isEmpty())
            <x-empty-state icon="classes" message="No classes found for this term.">
                @if ($canCopy && ! request()->hasAny(['search', 'status']))
                    <button type="button" class="btn btn-outline-secondary btn-sm-sies" data-bs-toggle="modal" data-bs-target="#copyClassesModal"><x-icon name="layers" :size="14" /> Copy from another term</button>
                @endif
                <button type="button" class="btn btn-navy btn-sm-sies" data-bs-toggle="modal" data-bs-target="#addClassModal"><x-icon name="plus" :size="14" /> Add class</button>
            </x-empty-state>
        @else
            <div class="table-responsive" data-fit-viewport>
                <table class="table table-sies table-hover">
                    <thead>
                        <tr><th scope="col">Class</th><th scope="col">Subject</th><th scope="col">Teacher</th><th scope="col">Schedule</th><th scope="col">Seats</th><th scope="col">Status</th><th scope="col" class="text-end">Actions</th></tr>
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
    </div>

    @include('classes._add-dialog')

    @if ($canCopy)
        {{-- Copy another term's classes into the term shown on this page. --}}
        <div class="modal fade" id="copyClassesModal" tabindex="-1" aria-labelledby="copyClassesTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" action="{{ route('classes.copy') }}" class="modal-content" novalidate>
                    @csrf
                    <input type="hidden" name="to_term" value="{{ $targetTerm->id }}">
                    <div class="modal-header">
                        <div>
                            <h2 class="modal-title h5 fw-bold" id="copyClassesTitle">Copy classes into this term</h2>
                            <div class="small text-secondary">Into {{ $targetTerm->name }} · {{ $targetTerm->academicYear->name }}</div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <label for="copyFrom" class="form-label fw-semibold small">Copy from</label>
                        <select id="copyFrom" name="from_term" class="form-select" required>
                            @foreach ($terms->where('id', '!=', $targetTerm->id) as $option)
                                <option value="{{ $option->id }}">{{ $option->name }} · {{ $option->academicYear->name }}{{ $option->is_current ? ' (current)' : '' }}</option>
                            @endforeach
                        </select>
                        <ul class="small text-secondary mt-3 mb-0 ps-3">
                            <li>Each class is copied with its subject, block, teacher, capacity and weekly schedule, and gets a new schedule code.</li>
                            <li>Copies start <strong>open</strong> with no students. Enrollment requests are not copied.</li>
                            <li>A class is skipped if this term already has it, its subject or teacher is inactive, or its time clashes here. You will see the list.</li>
                        </ul>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-navy px-4">Copy classes</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</x-app-layout>
