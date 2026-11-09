<x-app-layout title="Programs" subtitle="Degree programs offered by the school.">
    <div class="card-sies">
        <div class="card-header-sies">
            <form method="GET" class="filter-bar">
                <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search code or name">
                <button class="btn btn-outline-secondary btn-sm-sies">Search</button>
                @if (request('search')) <a href="{{ route('programs.index') }}" class="small">Clear</a> @endif
            </form>
            <button type="button" class="btn btn-navy btn-sm-sies px-3" data-bs-toggle="modal" data-bs-target="#programModal">Add program</button>
        </div>

        @if ($programs->isEmpty())
            <x-empty-state message="No programs yet. Add the first one." />
        @else
            <div class="table-responsive">
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
            <div class="p-3">{{ $programs->links() }}</div>
        @endif
    </div>

    <x-form-modal id="programModal" add-title="Add program" edit-title="Edit program"
        :store-url="route('programs.store')" :update-url="route('programs.update', '__ID__')">
        <x-field name="code" label="Program code" required maxlength="20" placeholder="e.g. BSCS" />
        <x-field name="name" label="Program name" required maxlength="150" placeholder="e.g. BS Computer Science" />
        <x-check name="is_active" label="Active" :checked="true" help="Inactive programs cannot be chosen for new students." />
    </x-form-modal>
</x-app-layout>
