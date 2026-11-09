<x-app-layout title="Subject Management" subtitle="Subjects that can be offered as classes.">
    <div class="card-sies">
        <div class="card-header-sies">
            <form method="GET" class="filter-bar">
                <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search code or title">
                <button class="btn btn-outline-secondary btn-sm-sies">Search</button>
                @if (request('search')) <a href="{{ route('subjects.index') }}" class="small">Clear</a> @endif
            </form>
            <button type="button" class="btn btn-navy btn-sm-sies px-3" data-bs-toggle="modal" data-bs-target="#subjectModal">Add subject</button>
        </div>

        @if ($subjects->isEmpty())
            <x-empty-state message="No subjects yet. Add the first one." />
        @else
            <div class="table-responsive">
                <table class="table table-sies table-hover">
                    <thead><tr><th>Code</th><th>Title</th><th>Units</th><th>Description</th><th>Classes</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                        @foreach ($subjects as $subject)
                            <tr>
                                <td class="fw-semibold">{{ $subject->code }}</td>
                                <td>{{ $subject->title }}</td>
                                <td>{{ rtrim(rtrim(number_format((float) $subject->units, 1), '0'), '.') }}</td>
                                <td class="text-secondary small">{{ \Illuminate\Support\Str::limit($subject->description, 60) }}</td>
                                <td>{{ $subject->class_offerings_count }}</td>
                                <td><x-status-badge :value="$subject->is_active ? 'active' : 'inactive'" /></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-outline-secondary btn-sm-sies"
                                        data-bs-toggle="modal" data-bs-target="#subjectModal"
                                        data-id="{{ $subject->id }}"
                                        data-action="{{ route('subjects.update', $subject) }}"
                                        data-values="{{ json_encode(['code' => $subject->code, 'title' => $subject->title, 'units' => (float) $subject->units, 'description' => $subject->description, 'is_active' => $subject->is_active]) }}">Edit</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-3">{{ $subjects->links() }}</div>
        @endif
    </div>

    <x-form-modal id="subjectModal" add-title="Add subject" edit-title="Edit subject"
        :store-url="route('subjects.store')" :update-url="route('subjects.update', '__ID__')">
        <x-field name="code" label="Subject code" required maxlength="20" placeholder="e.g. CS101" />
        <x-field name="title" label="Subject title" required maxlength="150" />
        <x-field name="units" type="number" label="Units" required step="0.5" min="0.5" max="12" />
        <x-field name="description" type="textarea" label="Description" maxlength="1000" />
        <x-check name="is_active" label="Active" :checked="true" help="Inactive subjects cannot be used for new classes." />
    </x-form-modal>
</x-app-layout>
