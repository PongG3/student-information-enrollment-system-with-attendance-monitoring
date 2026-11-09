@php($editing = $term->exists)
<x-app-layout :title="$editing ? 'Edit Term' : 'Add Term'" subtitle="Term dates must fall inside the academic year and may not overlap another term.">
    <div class="card-sies p-4 card-form">
        <form method="POST" action="{{ $editing ? route('terms.update', $term) : route('terms.store') }}" novalidate>
            @csrf
            @if ($editing) @method('PUT') @endif

            <x-select name="academic_year_id" label="Academic year"
                :options="$years->pluck('name', 'id')->all()" :value="$term->academic_year_id"
                placeholder="Choose an academic year" required :disabled="$editing" />
            @if ($editing)
                <input type="hidden" name="academic_year_id" value="{{ $term->academic_year_id }}">
            @endif

            <x-field name="name" label="Term name" :value="$term->name" required placeholder="e.g. 1st Semester" />
            <x-field name="start_date" type="date" label="Start date" :value="$term->start_date?->toDateString()" required />
            <x-field name="end_date" type="date" label="End date" :value="$term->end_date?->toDateString()" required />

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-navy px-4">{{ $editing ? 'Save changes' : 'Add term' }}</button>
                <a href="{{ route('academic-years.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
            </div>
        </form>
    </div>
</x-app-layout>
