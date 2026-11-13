<x-app-layout title="My Schedule" subtitle="Your weekly timetable for classes you are officially enrolled in.">
    <div class="card-sies p-3 mb-4">
        <form method="GET" class="filter-bar">
            <select name="term" class="form-select" onchange="this.form.submit()" aria-label="Term">
                @foreach ($terms as $option)
                    <option value="{{ $option->id }}" @selected($term && $term->id === $option->id)>
                        {{ $option->name }} · {{ $option->academicYear->name }}{{ $option->is_current ? ' (current)' : '' }}
                    </option>
                @endforeach
            </select>
            <span class="small text-secondary">{{ $classes->count() }} {{ \Illuminate\Support\Str::plural('class', $classes->count()) }} · {{ \App\Services\StudentPortalData::number($units) }} units</span>
            @if ($term && $classes->isNotEmpty())
                <a href="{{ route('student.registration-form', ['term' => $term->id]) }}" data-download class="btn btn-outline-secondary btn-sm-sies ms-auto">
                    <x-icon name="file" :size="14" /> Registration form (PDF)
                </a>
            @endif
        </form>
    </div>

    @if ($byDay === [])
        <div class="card-sies"><x-empty-state icon="calendar" message="You have no scheduled classes in this term." /></div>
    @else
        <div class="row g-4">
            @foreach ($byDay as $day => $items)
                <div class="col-lg-6">
                    <div @class(['card-sies', 'h-100', 'is-today' => $day === today()->dayOfWeekIso])>
                        <div class="card-header-sies">
                            <h2 class="h6 fw-bold mb-0">{{ \App\Models\ClassMeeting::DAYS[$day] }}</h2>
                            @if ($day === today()->dayOfWeekIso)<x-status-badge value="current" label="Today" />@endif
                        </div>
                        <ul class="list-group list-group-flush">
                            @foreach ($items as $item)
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between gap-3">
                                        <div>
                                            <div class="fw-semibold">{{ $item['class']->subject->code }} · {{ $item['class']->subject->title }}</div>
                                            <div class="small text-secondary">{{ $item['class']->class_code }} · {{ $item['class']->teacher->name }}</div>
                                        </div>
                                        <div class="text-end small text-nowrap">
                                            {{ \App\Services\ScheduleChecker::time($item['meeting']->start_time) }} to {{ \App\Services\ScheduleChecker::time($item['meeting']->end_time) }}
                                            <div class="text-secondary">{{ $item['meeting']->room ?: 'Room to be announced' }}</div>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-app-layout>
