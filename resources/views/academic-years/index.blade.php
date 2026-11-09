<x-app-layout title="Academic Years & Terms" subtitle="Set up the school calendar. Closing a year or term locks its records.">
    <x-slot:actions>
        <a href="{{ route('academic-years.create') }}" class="btn btn-navy"><x-icon name="plus" :size="16" /> Add academic year</a>
    </x-slot:actions>

    @forelse ($years as $year)
        <div class="card-sies mb-4">
            <div class="card-header-sies">
                <div>
                    <h2 class="h5 fw-bold mb-1">
                        {{ $year->name }}
                        @if ($year->is_current) <x-status-badge value="current" label="Current year" /> @endif
                        @if ($year->isClosed()) <x-status-badge value="closed" /> @endif
                    </h2>
                    <div class="text-secondary small">{{ $year->start_date->format('M j, Y') }} to {{ $year->end_date->format('M j, Y') }}</div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    @unless ($year->isClosed())
                        <a href="{{ route('terms.create', ['academic_year' => $year->id]) }}" class="btn btn-outline-secondary btn-sm-sies">Add term</a>
                        <a href="{{ route('academic-years.edit', $year) }}" class="btn btn-outline-secondary btn-sm-sies">Edit</a>
                        @unless ($year->is_current)
                            <button type="button" class="btn btn-outline-secondary btn-sm-sies"
                                data-bs-toggle="modal" data-bs-target="#confirmModal"
                                data-confirm-title="Make {{ $year->name }} current?"
                                data-confirm-message="Terms of other years will stop being current and close for enrollment."
                                data-confirm-action="{{ route('academic-years.current', $year) }}"
                                data-confirm-button="Make current">Make current</button>
                        @endunless
                        <button type="button" class="btn btn-outline-danger btn-sm-sies"
                            data-bs-toggle="modal" data-bs-target="#confirmModal"
                            data-confirm-title="Close {{ $year->name }}?"
                            data-confirm-message="The year and all its terms will be locked: no more enrollment or attendance changes."
                            data-confirm-action="{{ route('academic-years.close', $year) }}"
                            data-confirm-variant="danger" data-confirm-button="Close year">Close year</button>
                    @else
                        <button type="button" class="btn btn-outline-secondary btn-sm-sies"
                            data-bs-toggle="modal" data-bs-target="#confirmModal"
                            data-confirm-title="Reopen {{ $year->name }}?"
                            data-confirm-message="The year can be edited again. Its terms stay closed until reopened one by one."
                            data-confirm-action="{{ route('academic-years.reopen', $year) }}"
                            data-confirm-button="Reopen year">Reopen year</button>
                    @endunless
                </div>
            </div>

            @if ($year->terms->isEmpty())
                <x-empty-state icon="calendar" message="No terms in this year yet." />
            @else
                <div class="table-responsive">
                    <table class="table table-sies table-hover">
                        <thead><tr><th>Term</th><th>Dates</th><th>Status</th><th>Enrollment</th><th class="text-end">Actions</th></tr></thead>
                        <tbody>
                            @foreach ($year->terms as $term)
                                <tr>
                                    <td class="fw-semibold">{{ $term->name }}</td>
                                    <td>{{ $term->start_date->format('M j, Y') }} to {{ $term->end_date->format('M j, Y') }}</td>
                                    <td>
                                        @if ($term->isClosed()) <x-status-badge value="closed" />
                                        @elseif ($term->is_current) <x-status-badge value="current" label="Current term" />
                                        @else <span class="text-secondary small">Not current</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($term->enrollment_open) <x-status-badge value="open" label="Open" />
                                        @else <span class="text-secondary small">Closed</span>
                                        @endif
                                    </td>
                                    <td class="text-end text-nowrap">
                                        @unless ($term->isClosed())
                                            <a href="{{ route('terms.edit', $term) }}" class="btn btn-outline-secondary btn-sm-sies">Edit</a>
                                            @unless ($term->is_current)
                                                <button type="button" class="btn btn-outline-secondary btn-sm-sies"
                                                    data-bs-toggle="modal" data-bs-target="#confirmModal"
                                                    data-confirm-title="Make {{ $term->name }} current?"
                                                    data-confirm-message="Other terms stop being current and close for enrollment."
                                                    data-confirm-action="{{ route('terms.current', $term) }}"
                                                    data-confirm-button="Make current">Make current</button>
                                            @else
                                                <button type="button" class="btn btn-outline-secondary btn-sm-sies"
                                                    data-bs-toggle="modal" data-bs-target="#confirmModal"
                                                    data-confirm-title="{{ $term->enrollment_open ? 'Close enrollment?' : 'Open enrollment?' }}"
                                                    data-confirm-message="{{ $term->enrollment_open ? 'No new enrollment requests can be recorded for this term.' : 'Enrollment requests can be recorded for this term.' }}"
                                                    data-confirm-action="{{ route('terms.enrollment', $term) }}"
                                                    data-confirm-button="{{ $term->enrollment_open ? 'Close enrollment' : 'Open enrollment' }}">{{ $term->enrollment_open ? 'Close enrollment' : 'Open enrollment' }}</button>
                                            @endunless
                                            <button type="button" class="btn btn-outline-danger btn-sm-sies"
                                                data-bs-toggle="modal" data-bs-target="#confirmModal"
                                                data-confirm-title="Close {{ $term->name }}?"
                                                data-confirm-message="The term will be locked: no more enrollment or attendance changes."
                                                data-confirm-action="{{ route('terms.close', $term) }}"
                                                data-confirm-variant="danger" data-confirm-button="Close term">Close term</button>
                                        @elseif (! $year->isClosed())
                                            <button type="button" class="btn btn-outline-secondary btn-sm-sies"
                                                data-bs-toggle="modal" data-bs-target="#confirmModal"
                                                data-confirm-title="Reopen {{ $term->name }}?"
                                                data-confirm-message="The term can be edited and used again."
                                                data-confirm-action="{{ route('terms.reopen', $term) }}"
                                                data-confirm-button="Reopen term">Reopen term</button>
                                        @endunless
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @empty
        <div class="card-sies">
            <x-empty-state icon="calendar" message="No academic years yet.">
                <a href="{{ route('academic-years.create') }}" class="btn btn-navy btn-sm-sies"><x-icon name="plus" :size="14" /> Add academic year</a>
            </x-empty-state>
        </div>
    @endforelse
</x-app-layout>
