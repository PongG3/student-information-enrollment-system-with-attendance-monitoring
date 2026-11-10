<x-app-layout title="Programs" subtitle="Degree programs offered by the school.">
    <x-slot:actions>
        <button type="button" class="btn btn-navy" data-bs-toggle="modal" data-bs-target="#programModal"><x-icon name="plus" :size="16" /> Add program</button>
    </x-slot:actions>

    <div class="card-sies">
        <div class="card-header-sies">
            <form method="GET" class="filter-bar">
                <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search code or name">
                <button class="btn btn-outline-secondary btn-sm-sies">Search</button>
                @if (request('search')) <a href="{{ route('programs.index') }}" class="small">Clear</a> @endif
            </form>
        </div>

        @if ($programs->isEmpty())
            @if (request('search'))
                <x-empty-state message="Nothing matches your search." />
            @else
                <x-empty-state icon="layers" message="No programs yet.">
                    <button type="button" class="btn btn-navy btn-sm-sies" data-bs-toggle="modal" data-bs-target="#programModal"><x-icon name="plus" :size="14" /> Add program</button>
                </x-empty-state>
            @endif
        @else
            <div class="table-responsive" data-fit-viewport>
                <table class="table table-sies table-hover">
                    <thead><tr><th>Code</th><th>Name</th><th>Students</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                        @foreach ($programs as $program)
                            <tr>
                                <td class="fw-semibold">{{ $program->code }}</td>
                                <td>{{ $program->name }}</td>
                                <td>{{ $program->students_count }}</td>
                                <td><x-status-badge :value="$program->is_active ? 'active' : 'inactive'" /></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-outline-secondary btn-sm-sies"
                                        data-bs-toggle="modal" data-bs-target="#programModal"
                                        data-id="{{ $program->id }}"
                                        data-action="{{ route('programs.update', $program) }}"
                                        data-values="{{ json_encode(['code' => $program->code, 'name' => $program->name, 'is_active' => $program->is_active]) }}">Edit</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-table-footer :items="$programs" noun="program" />
        @endif
    </div>

    <x-form-modal id="programModal" add-title="Add program" edit-title="Edit program"
        :store-url="route('programs.store')" :update-url="route('programs.update', '__ID__')">
        <x-field name="code" label="Program code" required maxlength="20" placeholder="e.g. BSCS" />
        <x-field name="name" label="Program name" required maxlength="150" placeholder="e.g. BS Computer Science" />
        <x-check name="is_active" label="Active" :checked="true" help="Inactive programs cannot be chosen for new students." />
    </x-form-modal>
</x-app-layout>
