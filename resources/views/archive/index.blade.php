{{-- The Archive: records taken out of the active lists. Staff see their own archives; administrators see all,
     with who archived each one. Restore puts a record back; only administrators delete permanently. --}}
<x-app-layout title="Archive"
    :subtitle="$isAdmin ? 'Everything archived across the system. Restore a record to bring it back to its list.' : 'Records you archived. Restore one to bring it back to its list.'">

    <div class="card-sies">
        <div class="card-header-sies">
            {{-- Live: results update as you type (after a short pause) and when any filter changes. --}}
            <form method="GET" action="{{ route('archive.index') }}" class="filter-bar" role="search" aria-label="Filter the archive" data-live-filter>
                <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search name, ID or email"
                    aria-label="Search name, ID or email" autocomplete="off" aria-controls="archiveResults">
                <select name="type" class="form-select" aria-label="Record type">
                    <option value="">All types</option>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ \Illuminate\Support\Str::plural($label) }}</option>
                    @endforeach
                </select>
                @if ($isAdmin)
                    <select name="by" class="form-select" aria-label="Archived by">
                        <option value="">Archived by anyone</option>
                        @foreach ($archivers as $person)
                            <option value="{{ $person->id }}" @selected((string) request('by') === (string) $person->id)>{{ $person->name }}</option>
                        @endforeach
                    </select>
                @endif
                <label class="date-field">
                    <span>From</span>
                    <input type="date" name="from" value="{{ request('from') }}" max="{{ today()->toDateString() }}" class="form-control" aria-label="Archived from">
                </label>
                <label class="date-field">
                    <span>To</span>
                    <input type="date" name="to" value="{{ request('to') }}" max="{{ today()->toDateString() }}" class="form-control" aria-label="Archived until">
                </label>
                <noscript><button class="btn btn-outline-secondary btn-sm-sies">Filter</button></noscript>
                <span data-live-region="clear">@if ($filtered) <a href="{{ route('archive.index') }}" class="btn btn-link btn-sm p-0 small" data-live-clear>Clear</a> @endif</span>
            </form>
        </div>

        <span class="visually-hidden" data-live-region="status" aria-live="polite">{{ number_format($items->total()) }} archived {{ \Illuminate\Support\Str::plural('item', $items->total()) }} found</span>
        <div id="archiveResults" class="live-results" data-live-region="results">
            @if ($items->isEmpty())
                <x-empty-state icon="archive" :message="$filtered ? 'No archived items match your filters.' : 'No archived items found.'">
                    @if ($filtered)
                        <a href="{{ route('archive.index') }}" class="btn btn-outline-secondary btn-sm-sies" data-live-clear>Clear filters</a>
                    @elseif (! $isAdmin)
                        <span class="small text-secondary d-block">Records you archive appear here, and you can restore them at any time.</span>
                    @endif
                </x-empty-state>
            @else
                <div class="table-responsive" data-fit-viewport>
                    <table class="table table-sies table-hover align-middle table-stack">
                        <thead>
                            <tr>
                                <th scope="col">Name</th>
                                <th scope="col">Type</th>
                                <th scope="col">Archived</th>
                                @if ($isAdmin)<th scope="col">Archived by</th>@endif
                                <th scope="col" class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($items as $item)
                                <tr>
                                    <td>
                                        <a href="{{ $item['url'] }}" class="fw-semibold text-navy">{{ $item['name'] }}</a>
                                        <div class="small text-secondary">{{ $item['detail'] }}</div>
                                    </td>
                                    <td data-label="Type"><span class="program-chip">{{ $types[$item['type']] }}</span></td>
                                    <td data-label="Archived">
                                        <time datetime="{{ $item['archived_at']->toIso8601String() }}" title="{{ $item['archived_at']->format('F j, Y g:i A') }}">{{ $item['archived_at']->format('M j, Y') }}</time>
                                        <div class="small text-secondary">{{ $item['archived_at']->diffForHumans() }}</div>
                                    </td>
                                    @if ($isAdmin)
                                        <td data-label="Archived by">{!! $item['archived_by'] ? e($item['archived_by']) : '<span class="text-secondary">Unknown</span>' !!}</td>
                                    @endif
                                    <td class="text-end text-nowrap">
                                        <button type="button" class="btn btn-outline-success btn-sm-sies" data-bs-toggle="modal" data-bs-target="#confirmModal"
                                            data-confirm-title="Restore {{ $item['name'] }}?"
                                            data-confirm-message="It goes back to its active list with all its history."
                                            data-confirm-action="{{ route('archive.restore', [$item['type'], $item['id']]) }}"
                                            data-confirm-button="Restore">Restore</button>
                                        @if ($isAdmin)
                                            <button type="button" class="btn btn-quiet-danger btn-sm-sies" data-bs-toggle="modal" data-bs-target="#confirmModal"
                                                data-confirm-title="Delete {{ $item['name'] }} permanently?"
                                                data-confirm-message="This cannot be undone. The record, its documents and its portal account are erased. Records with enrollment or attendance history are kept and cannot be deleted."
                                                data-confirm-action="{{ route('archive.destroy', [$item['type'], $item['id']]) }}"
                                                data-confirm-method="DELETE" data-confirm-variant="danger"
                                                data-confirm-button="Delete permanently">Delete</button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <x-table-footer :items="$items" noun="archived item" />
            @endif
        </div>
    </div>
</x-app-layout>
