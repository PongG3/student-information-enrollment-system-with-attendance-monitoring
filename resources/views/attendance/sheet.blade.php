@php
    $editable = $access['mode'] === 'edit';
    $labels = ['present' => 'Present', 'late' => 'Late', 'absent' => 'Absent', 'excused' => 'Excused'];
    $colors = ['present' => 'success', 'late' => 'warning', 'absent' => 'danger', 'excused' => 'info'];
@endphp
<x-app-layout :title="$class->class_code . ' · ' . $class->subject->title"
    :subtitle="$date->format('l, F j, Y') . ' · ' . $class->term->name . ' · ' . $class->term->academicYear->name"
    :back="route('attendance.index')" back-label="Back to attendance">
    @foreach (array_merge($errors->get('sheet'), $errors->get('rows.*') ? array_merge(...array_values($errors->get('rows.*'))) : [], $errors->get('rows'), $errors->get('reason')) as $message)
        <div class="alert alert-danger py-2">{{ $message }}</div>
    @endforeach

    <div class="row g-4">
        {{-- On phones the sheet comes first (teachers take attendance in class); dates and class details follow it. --}}
        <div class="col-lg-3 order-2">
            <div class="card-sies p-3 mb-3">
                <h2 class="h6 fw-bold">Choose a date</h2>
                <form method="GET" action="{{ route('attendance.sheet') }}" class="d-flex gap-2 mb-3">
                    <input type="hidden" name="class_offering" value="{{ $class->id }}">
                    <input type="date" name="date" value="{{ $date->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control" aria-label="Date">
                    <button class="btn btn-outline-secondary btn-sm-sies">Go</button>
                </form>
                <div class="small text-secondary mb-2">Recent class days</div>
                <div class="class-days">
                @forelse ($classDays as $day)
                    <a href="{{ route('attendance.sheet', ['class_offering' => $class->id, 'date' => $day['date']->toDateString()]) }}"
                       class="d-flex justify-content-between align-items-center text-decoration-none small py-1 {{ $day['date']->isSameDay($date) ? 'fw-bold' : '' }}">
                        <span>{{ $day['date']->format('D, M j') }}</span>
                        @if ($day['recorded']) <x-status-badge value="verified" label="Recorded" /> @else <x-status-badge value="pending" label="Not yet" /> @endif
                    </a>
                @empty
                    <div class="small text-secondary">No class days yet.</div>
                @endforelse
                </div>
            </div>
            <div class="card-sies p-3 small">
                <div class="fw-semibold">{{ $class->subject->code }} · {{ $class->block_label }}</div>
                <div class="text-secondary">{{ $class->teacher->name }}</div>
                <div class="text-secondary">{{ $class->scheduleSummary() }}</div>
                <a href="{{ route('attendance.summary', $class) }}" class="d-inline-block mt-2 fw-semibold text-decoration-none">Attendance summary →</a>
            </div>
        </div>

        <div class="col-lg-9 order-1">
            {{-- Phones: a compact date picker above the list (the full panel with recent days is below the sheet). --}}
            <form method="GET" action="{{ route('attendance.sheet') }}" class="d-flex d-lg-none gap-2 mb-2">
                <input type="hidden" name="class_offering" value="{{ $class->id }}">
                <input type="date" name="date" value="{{ $date->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control" aria-label="Date">
                <button class="btn btn-outline-secondary btn-sm-sies">Go</button>
            </form>

            @if (! $editable && $access['messages'])
                <div class="alert alert-secondary">
                    @foreach ($access['messages'] as $message)<div>{{ $message }}</div>@endforeach
                </div>
            @elseif (! $editable)
                <div class="alert alert-secondary">This sheet is read-only for your role.</div>
            @endif

            @if ($session)
                <div class="small text-secondary mb-2">Recorded by {{ $session->takenBy->name }} on {{ $session->created_at->format('M j, Y g:i A') }}.</div>
            @elseif ($editable)
                <div class="small text-secondary mb-2">No sheet yet for this date. Everyone starts as Present. Change the exceptions, then save.</div>
            @endif
            @php $waiting = $excuses->where('status', 'pending')->count(); @endphp
            @if ($editable && $waiting > 0)
                <div class="alert alert-info py-2 small">{{ $waiting }} excuse {{ \Illuminate\Support\Str::plural('letter', $waiting) }} waiting. Set the student to <strong>Excused</strong> to accept it; saving any other status declines it.</div>
            @endif

            <form method="POST" action="{{ route('attendance.save') }}" novalidate @if ($editable) data-attendance-sheet @endif>
                @csrf
                <input type="hidden" name="class_offering" value="{{ $class->id }}">
                <input type="hidden" name="date" value="{{ $date->toDateString() }}">

                <div class="card-sies">
                    <div class="card-header-sies">
                        <div>
                            <h2 class="h6 fw-bold mb-0">{{ $students->count() }} {{ \Illuminate\Support\Str::plural('student', $students->count()) }}</h2>
                            @if ($editable && $session) <span class="small text-secondary">Editing a saved sheet. Changes are logged.</span> @endif
                        </div>
                        @if ($editable && $students->isNotEmpty())
                            <button type="button" class="btn btn-outline-secondary btn-sm-sies" data-mark-all="present">Mark everyone present</button>
                        @endif
                    </div>

                    @if ($students->isEmpty())
                        <x-empty-state icon="students" message="No enrolled students for this date." />
                    @else
                        <div class="table-responsive" data-fit-viewport>
                            <table class="table table-sies align-middle">
                                <thead><tr><th>Student</th><th>Status</th><th>Remarks</th></tr></thead>
                                <tbody>
                                    @foreach ($students as $student)
                                        @php
                                            $record = $records->get($student->id);
                                            $current = old("rows.{$student->id}.status", $record?->status ?? ($session ? null : 'present'));
                                        @endphp
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">{{ $student->full_name }}</div>
                                                <div class="text-secondary small">{{ $student->student_number }}</div>
                                                @if ($excuse = $excuses->get($student->id))
                                                    {{-- Excuse letter: saving the sheet as Excused accepts it; any other status declines it. --}}
                                                    <div class="excuse-note small mt-1">
                                                        <x-status-badge :value="['pending' => 'pending', 'accepted' => 'verified', 'declined' => 'rejected'][$excuse->status]"
                                                            :label="'Excuse letter' . ($excuse->status === 'pending' ? '' : ' ' . $excuse->status)" />
                                                        <span class="text-secondary">“{{ \Illuminate\Support\Str::limit($excuse->reason, 90) }}”</span>
                                                        @if ($excuse->file_path)
                                                            <a href="{{ route('excuses.file', $excuse) }}" target="_blank" class="fw-semibold text-decoration-none">View file</a>
                                                        @endif
                                                    </div>
                                                @endif
                                            </td>
                                            @if ($editable)
                                                <td>
                                                    <div class="btn-group" role="group" aria-label="Status of {{ $student->full_name }}">
                                                        @foreach ($labels as $value => $label)
                                                            <input type="radio" class="btn-check" name="rows[{{ $student->id }}][status]" value="{{ $value }}"
                                                                id="s{{ $student->id }}{{ $value }}" @checked($current === $value)>
                                                            <label class="btn btn-outline-{{ $colors[$value] }} btn-sm-sies" for="s{{ $student->id }}{{ $value }}">{{ $label }}</label>
                                                        @endforeach
                                                    </div>
                                                </td>
                                                <td>
                                                    <input type="text" name="rows[{{ $student->id }}][remarks]" maxlength="255" class="form-control"
                                                        value="{{ old("rows.{$student->id}.remarks", $record?->remarks ?: ($excuse && $excuse->status === 'pending' ? 'Excuse letter' : null)) }}" placeholder="Required for Excused" aria-label="Remarks for {{ $student->full_name }}">
                                                </td>
                                            @else
                                                <td>@if ($record) <x-status-badge :value="$record->status" /> @else <span class="text-secondary small">Not recorded</span> @endif</td>
                                                <td class="small text-secondary">{{ $record?->remarks }}</td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                @if ($editable && $students->isNotEmpty())
                    @if ($access['needsReason'])
                        <div class="card-sies p-3 mt-3">
                            <label for="reason" class="form-label fw-semibold small">Reason for changing an older sheet <span class="text-danger">*</span></label>
                            <input id="reason" type="text" name="reason" maxlength="255" class="form-control" value="{{ old('reason') }}" placeholder="Why is this sheet being recorded or corrected now?">
                            <div class="form-text">This date is outside the teacher edit window. The reason is saved in the audit log.</div>
                        </div>
                    @endif
                    <div class="form-actionbar is-sticky mt-3">
                        <div class="attendance-tally" aria-live="polite">
                            @foreach ($labels as $value => $label)
                                <span class="tally tally-{{ $colors[$value] }}"><strong data-tally="{{ $value }}">0</strong> {{ $label }}</span>
                            @endforeach
                        </div>
                        <div class="form-actions ms-sm-auto">
                            <a href="{{ route('attendance.index') }}" class="btn btn-outline-secondary px-4">Back</a>
                            <button type="submit" class="btn btn-navy px-4">{{ $session ? 'Save changes' : 'Save attendance' }}</button>
                        </div>
                    </div>
                @endif
            </form>
        </div>
    </div>
</x-app-layout>
