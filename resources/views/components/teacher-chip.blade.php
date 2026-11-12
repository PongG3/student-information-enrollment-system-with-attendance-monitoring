@props(['teacher'])
{{-- A teacher as students see them: photo (or initials), name and department. Never the contact number. --}}
@if ($teacher)
    <span class="teacher-chip">
        @if ($teacher->photoUrl())
            <img src="{{ $teacher->photoUrl() }}" alt="" class="teacher-chip-photo">
        @else
            <span class="teacher-chip-photo" aria-hidden="true">{{ $teacher->initials() }}</span>
        @endif
        <span class="min-w-0">
            <span class="d-block">{{ $teacher->name }}</span>
            @if ($teacher->department)<span class="d-block small text-secondary text-truncate">{{ $teacher->department }}</span>@endif
        </span>
    </span>
@else
    <span class="text-secondary">To be announced</span>
@endif
