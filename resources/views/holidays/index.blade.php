<x-app-layout title="Holidays & Non-class Days" subtitle="These dates are never counted as absences.">
    <x-slot:actions>
        <button type="button" class="btn btn-navy" data-bs-toggle="modal" data-bs-target="#holidayModal"><x-icon name="plus" :size="16" /> Add non-class day</button>
    </x-slot:actions>

    <div class="card-sies">
        <div class="card-header-sies">
            <form method="GET" class="filter-bar">
                <select name="year" class="form-select" onchange="this.form.submit()">
                    @foreach ($years as $y)
                        <option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>
                    @endforeach
                </select>
                <noscript><button class="btn btn-outline-secondary btn-sm-sies">Show</button></noscript>
            </form>
        </div>

        @if ($holidays->isEmpty())
            <x-empty-state icon="calendar" :message="'No non-class days recorded for ' . $year . '.'" />
        @else
            <div class="table-responsive">
                <table class="table table-sies table-hover">
                    <thead><tr><th>Date</th><th>Day</th><th>Description</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                        @foreach ($holidays as $holiday)
                            <tr>
                                <td class="fw-semibold">{{ $holiday->date->format('M j, Y') }}</td>
                                <td>{{ $holiday->date->format('l') }}</td>
                                <td>{{ $holiday->description }}</td>
                                <td class="text-end text-nowrap">
                                    <button type="button" class="btn btn-outline-secondary btn-sm-sies"
                                        data-bs-toggle="modal" data-bs-target="#holidayModal"
                                        data-id="{{ $holiday->id }}"
                                        data-action="{{ route('holidays.update', $holiday) }}"
                                        data-values="{{ json_encode(['date' => $holiday->date->toDateString(), 'description' => $holiday->description]) }}">Edit</button>
                                    <button type="button" class="btn btn-outline-danger btn-sm-sies"
                                        data-bs-toggle="modal" data-bs-target="#confirmModal"
                                        data-confirm-title="Remove this day?"
                                        data-confirm-message="{{ $holiday->date->format('M j, Y') }} will count as a normal class day again."
                                        data-confirm-action="{{ route('holidays.destroy', $holiday) }}"
                                        data-confirm-method="DELETE" data-confirm-variant="danger"
                                        data-confirm-button="Remove">Remove</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <x-form-modal id="holidayModal" add-title="Add non-class day" edit-title="Edit non-class day"
        :store-url="route('holidays.store')" :update-url="route('holidays.update', '__ID__')">
        <x-field name="date" type="date" label="Date" required />
        <x-field name="description" label="Description" required maxlength="150" placeholder="e.g. Christmas Day or Class suspension" />
    </x-form-modal>
</x-app-layout>
