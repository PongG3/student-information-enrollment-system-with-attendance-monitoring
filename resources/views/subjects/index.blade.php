<x-app-layout title="Subject Management" subtitle="Subjects that can be offered as classes.">
    <x-slot:actions>
        <button type="button" class="btn btn-navy" data-bs-toggle="modal" data-bs-target="#subjectModal"><x-icon name="plus" :size="16" /> Add subject</button>
    </x-slot:actions>

    <div class="card-sies">
        <div class="card-header-sies">
            {{-- Live: results update as you type (after a short pause) and when any filter changes. --}}
            <form method="GET" action="{{ route('subjects.index') }}" class="filter-bar" role="search" aria-label="Filter subjects" data-live-filter>
                <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search code or title" aria-label="Search code or title">
                <select name="category" class="form-select" aria-label="Category">
                    <option value="">Major and minor</option>
                    @foreach (\App\Models\Subject::CATEGORIES as $value => $label)
                        <option value="{{ $value }}" @selected(request('category') === $value)>{{ $label }} subjects</option>
                    @endforeach
                </select>
                <noscript><button class="btn btn-outline-secondary btn-sm-sies">Filter</button></noscript>
                <span data-live-region="clear">@if (request()->hasAny(['search', 'category'])) <a href="{{ route('subjects.index') }}" class="btn btn-link btn-sm p-0 small" data-live-clear>Clear</a> @endif</span>
            </form>
        </div>

        <span class="visually-hidden" data-live-region="status" aria-live="polite">{{ number_format($subjects->total()) }} {{ \Illuminate\Support\Str::plural('subject', $subjects->total()) }} found</span>
        <div class="live-results" data-live-region="results">
        @if ($subjects->isEmpty())
            @if (request()->hasAny(['search', 'category']))
                <x-empty-state message="Nothing matches your filters." />
            @else
                <x-empty-state icon="book" message="No subjects yet.">
                    <button type="button" class="btn btn-navy btn-sm-sies" data-bs-toggle="modal" data-bs-target="#subjectModal"><x-icon name="plus" :size="14" /> Add subject</button>
                </x-empty-state>
            @endif
        @else
            <div class="table-responsive" data-fit-viewport>
                <table class="table table-sies table-hover">
                    <thead><tr><th scope="col">Code</th><th scope="col">Title</th><th scope="col">Units</th><th scope="col">Category</th><th scope="col">Programs</th><th scope="col">Classes</th><th scope="col">Status</th><th scope="col" class="text-end">Actions</th></tr></thead>
                    <tbody>
                        @foreach ($subjects as $subject)
                            <tr>
                                <td class="fw-semibold">{{ $subject->code }}</td>
                                <td>
                                    {{ $subject->title }}
                                    @if ($subject->prerequisites->isNotEmpty())
                                        <div class="small text-secondary">Needs {{ $subject->prerequisites->pluck('code')->implode(', ') }}</div>
                                    @endif
                                </td>
                                <td>{{ rtrim(rtrim(number_format((float) $subject->units, 1), '0'), '.') }}</td>
                                <td><span class="category-badge is-{{ $subject->category }}">{{ $subject->categoryLabel() }}</span></td>
                                <td>
                                    @if ($subject->isGeneral())
                                        <span class="text-secondary small">All programs</span>
                                    @else
                                        @foreach ($subject->programs as $program)<span class="program-chip">{{ $program->code }}</span>@endforeach
                                    @endif
                                </td>
                                <td>{{ $subject->class_offerings_count }}</td>
                                <td><x-status-badge :value="$subject->is_active ? 'active' : 'inactive'" /></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-outline-secondary btn-sm-sies"
                                        data-bs-toggle="modal" data-bs-target="#subjectModal"
                                        data-id="{{ $subject->id }}"
                                        data-action="{{ route('subjects.update', $subject) }}"
                                        data-values="{{ json_encode(['code' => $subject->code, 'title' => $subject->title, 'units' => (float) $subject->units, 'description' => $subject->description, 'category' => $subject->category, 'is_active' => $subject->is_active, 'program_ids' => $subject->programs->pluck('id')->all(), 'prerequisite_ids' => $subject->prerequisites->pluck('id')->all()]) }}">Edit</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-table-footer :items="$subjects" noun="subject" />
        @endif
        </div>
    </div>

    <x-form-modal id="subjectModal" add-title="Add subject" edit-title="Edit subject"
        :store-url="route('subjects.store')" :update-url="route('subjects.update', '__ID__')" size="modal-lg">
        {{-- Two columns: what the subject is (left), who may take it (right). --}}
        <div class="row g-4">
        <div class="col-md-6">
        <div class="row">
            <div class="col-7"><x-field name="code" label="Subject code" required maxlength="20" placeholder="e.g. CS101" /></div>
            <div class="col-5"><x-field name="units" type="number" label="Units" required step="0.5" min="0.5" max="12" /></div>
        </div>
        <x-field name="title" label="Subject title" required maxlength="150" />
        <x-field name="description" type="textarea" label="Description (optional)" maxlength="1000" />
        <x-check name="is_active" label="Active" :checked="true" help="Inactive subjects cannot be used for new classes." />
        </div>
        <div class="col-md-6">
        <fieldset class="mb-3" data-category-choice>
            <legend class="form-label mb-1">Category <span class="text-danger">*</span></legend>
            <div class="category-choice">
                <label class="category-option">
                    <input class="form-check-input" type="radio" name="category" value="major" @checked(old('category') === 'major')>
                    <span><strong>Major</strong><span class="d-block small text-secondary">A program's specialized subject, only for the programs you choose.</span></span>
                </label>
                <label class="category-option">
                    <input class="form-check-input" type="radio" name="category" value="minor" @checked(old('category', 'minor') === 'minor')>
                    <span><strong>Minor</strong><span class="d-block small text-secondary">General education or PE (Ethics, Purposive Communication...), open to every program.</span></span>
                </label>
            </div>
        </fieldset>
        <fieldset class="mb-3" data-major-only>
            <legend class="form-label mb-1">Programs <span class="text-danger">*</span></legend>
            <div class="form-text mt-0 mb-2">Tick every program whose curriculum has this major subject.</div>
            <div class="program-checks">
                @foreach ($programs as $program)
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="program_ids[]" value="{{ $program->id }}" id="subjectProgram{{ $program->id }}"
                            @checked(in_array((string) $program->id, array_map('strval', (array) old('program_ids', [])), true))>
                        <label class="form-check-label" for="subjectProgram{{ $program->id }}"><strong>{{ $program->code }}</strong> <span class="text-secondary small">{{ $program->name }}</span></label>
                    </div>
                @endforeach
            </div>
            @error('program_ids')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </fieldset>
        <fieldset class="mb-3">
            <legend class="form-label mb-1">Prerequisites <span class="text-secondary fw-normal small">(optional)</span></legend>
            <div class="form-text mt-0 mb-2">Subjects a student must have completed in an earlier term, e.g. Programming 1 before Programming 2.</div>
            <div class="program-checks prerequisite-checks">
                @foreach ($allSubjects as $option)
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="prerequisite_ids[]" value="{{ $option->id }}" id="subjectPrereq{{ $option->id }}"
                            @checked(in_array((string) $option->id, array_map('strval', (array) old('prerequisite_ids', [])), true))>
                        <label class="form-check-label" for="subjectPrereq{{ $option->id }}"><strong>{{ $option->code }}</strong> <span class="text-secondary small">{{ $option->title }}</span></label>
                    </div>
                @endforeach
            </div>
            @error('prerequisite_ids')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </fieldset>
        </div>
        </div>
    </x-form-modal>
</x-app-layout>
