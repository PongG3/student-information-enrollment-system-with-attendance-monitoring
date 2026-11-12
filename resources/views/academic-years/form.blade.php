@php($editing = $year->exists)
<x-app-layout :title="$editing ? 'Edit Academic Year' : 'Add Academic Year'" subtitle="Dates may not overlap another academic year.">
    <div class="card-sies p-4 card-form">
        <form method="POST" action="{{ $editing ? route('academic-years.update', $year) : route('academic-years.store') }}" novalidate>
            @csrf
            @if ($editing) @method('PUT') @endif

            <x-field name="name" label="Name" :value="$year->name" required placeholder="e.g. 2026-2027" help="Format: 2026-2027" />
            <x-field name="start_date" type="date" label="Start date" :value="$year->start_date?->toDateString()" required />
            <x-field name="end_date" type="date" label="End date" :value="$year->end_date?->toDateString()" required />

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-navy px-4">{{ $editing ? 'Save changes' : 'Add academic year' }}</button>
                <a href="{{ route('academic-years.index') }}" class="btn btn-outline-secondary px-4" data-dialog-close>Cancel</a>
            </div>
        </form>
    </div>
</x-app-layout>
