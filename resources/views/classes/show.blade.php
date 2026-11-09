<x-app-layout :title="$class->class_code . ' · ' . $class->subject->title"
    :subtitle="$class->term->name . ' · ' . $class->term->academicYear->name"
    :back="$isStaff ? route('classes.index', ['term' => $class->term_id]) : route('teacher.classes', ['term' => $class->term_id])"
    back-label="Back to classes">
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card-sies p-4 h-100">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h2 class="h6 fw-bold mb-0">Class details</h2>
                    <x-status-badge :value="$class->status" />
                </div>
                <dl class="mb-0 small">
                    <dt class="text-secondary">Subject</dt>
                    <dd>{{ $class->subject->code }} · {{ $class->subject->title }} ({{ rtrim(rtrim(number_format((float) $class->subject->units, 1), '0'), '.') }} units)</dd>
                    <dt class="text-secondary">Schedule code</dt>
                    <dd class="fw-semibold">{{ $class->class_code }}</dd>
                    <dt class="text-secondary">Block</dt>
                    <dd>{{ $class->block_label }}@unless ($class->program_id) <span class="text-secondary">· open to any block</span>@endunless</dd>
                    <dt class="text-secondary">Teacher</dt>
                    <dd>{{ $class->teacher->name }}</dd>
                    <dt class="text-secondary">Schedule</dt>
                    <dd>
                        @foreach ($class->meetings->sortBy(['day_of_week', 'start_time']) as $meeting)
                            <div>{{ \App\Models\ClassMeeting::DAYS[$meeting->day_of_week] }}
                                {{ \App\Services\ScheduleChecker::time($meeting->start_time) }} to {{ \App\Services\ScheduleChecker::time($meeting->end_time) }}
                                @if ($meeting->room) · {{ $meeting->room }} @endif</div>
                        @endforeach
                    </dd>
                    <dt class="text-secondary">Seats</dt>
                    <dd>{{ $seatsTaken }} taken of {{ $class->capacity }}</dd>
                </dl>

                <div class="d-flex flex-wrap gap-2 mt-3">
                    @if ($isStaff && ! $class->term->isClosed())
                        <a href="{{ route('classes.edit', $class) }}" class="btn btn-outline-secondary btn-sm-sies">Edit class</a>
                    @endif
                    <a href="{{ route('attendance.sheet', ['class_offering' => $class->id]) }}" class="btn btn-outline-secondary btn-sm-sies">Attendance sheet</a>
                    <a href="{{ route('attendance.summary', $class) }}" class="btn btn-outline-secondary btn-sm-sies">Attendance summary</a>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card-sies">
                <div class="card-header-sies">
                    <h2 class="h6 fw-bold mb-0">{{ $isStaff ? 'Students who requested this class' : 'Class roster' }}</h2>
                    <span class="text-secondary small">{{ $roster->count() }} {{ \Illuminate\Support\Str::plural('student', $roster->count()) }}</span>
                </div>

                @if ($roster->isEmpty())
                    <x-empty-state icon="students" :message="$isStaff ? 'No student has requested this class yet.' : 'No students are enrolled yet.'" />
                @else
                    <div class="table-responsive">
                        <table class="table table-sies table-hover">
                            <thead>
                                <tr><th>Student ID</th><th>Name</th><th>Program</th><th>Year</th>@if ($isStaff)<th>Status</th>@endif</tr>
                            </thead>
                            <tbody>
                                @foreach ($roster as $enrollment)
                                    @php($student = $enrollment->student)
                                    <tr>
                                        <td class="fw-semibold">
                                            @if ($isStaff)
                                                <a href="{{ route('students.show', $student) }}" class="text-decoration-none">{{ $student->student_number }}</a>
                                            @else
                                                {{ $student->student_number }}
                                            @endif
                                        </td>
                                        <td>{{ $student->full_name }}</td>
                                        <td>{{ $student->program->code }}</td>
                                        <td>{{ $student->year_level }}</td>
                                        @if ($isStaff)<td><x-status-badge :value="$enrollment->status" /></td>@endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
