<x-app-layout title="Search" :subtitle="$searched ? 'Results for “' . $query . '”' : 'Type at least two letters in the search box above.'">
    @if (! $searched)
        <div class="card-sies"><x-empty-state message="Search by student name or number, schedule code, or subject." /></div>
    @else
        @if ($showStudents)
            <div class="card-sies mb-4">
                <div class="card-header-sies"><h2 class="h6 fw-bold mb-0">Students</h2><span class="small text-secondary">Archived students are not shown</span></div>
                @if ($students->isEmpty())
                    <x-empty-state icon="students" message="No students match." />
                @else
                    <ul class="list-group list-group-flush">
                        @foreach ($students as $student)
                            <li class="list-group-item">
                                <a href="{{ route('students.show', $student) }}" class="text-decoration-none d-flex justify-content-between gap-2">
                                    <span><span class="fw-semibold">{{ $student->full_name }}</span> <span class="small text-secondary">· {{ $student->student_number }}</span></span>
                                    <span class="small text-secondary">{{ $student->program?->code }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif

        <div class="card-sies">
            <div class="card-header-sies"><h2 class="h6 fw-bold mb-0">{{ $showStudents ? 'Classes' : 'My classes' }}</h2></div>
            @if ($classes->isEmpty())
                <x-empty-state icon="classes" message="No classes match." />
            @else
                <ul class="list-group list-group-flush">
                    @foreach ($classes as $class)
                        <li class="list-group-item">
                            <a href="{{ route('classes.show', $class) }}" class="text-decoration-none d-flex justify-content-between gap-2">
                                <span><span class="fw-semibold">{{ $class->class_code }}</span> <span class="small text-secondary">· {{ $class->subject->code }} {{ $class->subject->title }}</span></span>
                                <span class="small text-secondary">{{ $class->term?->name }} · {{ $class->term?->academicYear?->name }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
</x-app-layout>
