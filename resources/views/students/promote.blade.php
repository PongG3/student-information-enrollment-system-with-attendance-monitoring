{{-- End of the academic year: move active students up one year level. Preview first; untick anyone who stays. --}}
<x-app-layout title="Promote year level" subtitle="At the end of the academic year, move students up one year level. Untick anyone who stays in the same year."
    :back="route('students.index')" back-label="Back to student records">

    @if ($lastRunAt)
        <div class="alert alert-info small py-2">
            Last done on <strong>{{ $lastRunAt->format('F j, Y g:i A') }}</strong>{{ $lastRunBy ? ' by ' . $lastRunBy : '' }}.
            Do this once a year only, or students will move up twice.
        </div>
    @endif

    <div class="card-sies p-3 mb-3">
        <form method="GET" class="filter-bar">
            <select name="program" class="form-select" aria-label="Program" onchange="this.form.submit()">
                <option value="">All programs</option>
                @foreach ($programs as $option)
                    <option value="{{ $option->id }}" @selected((string) $program === (string) $option->id)>{{ $option->code }} · {{ $option->name }}</option>
                @endforeach
            </select>
            <label class="date-field">
                <span>Last year level</span>
                <select name="last_year" class="form-select" onchange="this.form.submit()" aria-label="Last year level of the program">
                    @foreach (range(2, 6) as $year)
                        <option value="{{ $year }}" @selected($lastYear === $year)>Year {{ $year }}</option>
                    @endforeach
                </select>
            </label>
            <span class="small text-secondary">Students already in year {{ $lastYear }} are not moved.</span>
        </form>
    </div>

    <form method="POST" action="{{ route('students.promote.store') }}" novalidate>
        @csrf
        <input type="hidden" name="last_year" value="{{ $lastYear }}">
        @foreach (['students', 'confirm'] as $field)
            @error($field)<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror
        @endforeach

        @if ($students->isEmpty())
            <div class="card-sies"><x-empty-state icon="students" message="No active students to move up." /></div>
        @else
            @php $movable = $students->where('year_level', '<', $lastYear)->count(); @endphp
            <div class="card-sies">
                <div class="card-header-sies">
                    <div>
                        <h2 class="h6 fw-bold mb-0">{{ $movable }} of {{ $students->count() }} active {{ \Illuminate\Support\Str::plural('student', $students->count()) }} will move up</h2>
                        <span class="small text-secondary">Untick a student to keep them in the same year level.</span>
                    </div>
                </div>
                <div class="table-responsive" data-fit-viewport>
                    <table class="table table-sies align-middle mb-0">
                        <thead><tr><th scope="col" style="width: 3rem"><span class="visually-hidden">Move up</span></th><th scope="col">Student</th><th scope="col">Program</th><th scope="col">Year level</th></tr></thead>
                        <tbody>
                            @foreach ($groups as $group => $rows)
                                <tr class="request-student"><td colspan="4" class="small fw-semibold">{{ $group }} <span class="text-secondary fw-normal">· {{ $rows->count() }}</span></td></tr>
                                @foreach ($rows as $student)
                                    @php $final = $student->year_level >= $lastYear; @endphp
                                    <tr @class(['text-secondary' => $final])>
                                        <td>
                                            @unless ($final)
                                                <input class="form-check-input" type="checkbox" name="students[]" value="{{ $student->id }}" id="promote{{ $student->id }}"
                                                    @checked(! old() || in_array((string) $student->id, (array) old('students', []), true))
                                                    aria-label="Move {{ $student->full_name }} up">
                                            @endunless
                                        </td>
                                        <td><label for="promote{{ $student->id }}" class="mb-0"><span class="fw-semibold">{{ $student->full_name }}</span> <span class="small text-secondary">{{ $student->student_number }}</span></label></td>
                                        <td>{{ $student->program?->code }}</td>
                                        <td>
                                            @if ($final)
                                                Year {{ $student->year_level }} <span class="small">· last year, not moved</span>
                                            @else
                                                Year {{ $student->year_level }} → <strong>Year {{ $student->year_level + 1 }}</strong>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="form-actionbar is-sticky mt-3">
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" name="confirm" value="1" id="promoteConfirm">
                    <label class="form-check-label small" for="promoteConfirm">I checked the list. Move the ticked students up one year level.</label>
                </div>
                <div class="form-actions ms-sm-auto">
                    <a href="{{ route('students.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
                    <button type="submit" class="btn btn-navy px-4">Move students up</button>
                </div>
            </div>
        @endif
    </form>
</x-app-layout>
