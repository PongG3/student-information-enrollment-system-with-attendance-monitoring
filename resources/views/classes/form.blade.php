@php($editing = $class->exists)
<x-app-layout :title="$editing ? 'Edit Class' : 'Add Class'"
    subtitle="Teacher and room conflicts are checked when you save.">
    <form method="POST" action="{{ $editing ? route('classes.update', $class) : route('classes.store') }}" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="card-sies p-4 mb-4">
            <h2 class="h6 fw-bold mb-3">Class details</h2>

            @if ($hasEnrollments)
                <div class="alert alert-info small">Students have requested this class, so its term and subject cannot change.</div>
            @endif

            <div class="row">
                <div class="col-md-6">
                    <x-select name="term_id" label="Term"
                        :options="$terms->mapWithKeys(fn ($t) => [$t->id => $t->name . ' · ' . $t->academicYear->name])->all()"
                        :value="$class->term_id" placeholder="Choose a term" required :disabled="$hasEnrollments" />
                    @if ($hasEnrollments)<input type="hidden" name="term_id" value="{{ $class->term_id }}">@endif
                </div>
                <div class="col-md-6">
                    <x-select name="subject_id" label="Subject"
                        :options="$subjects->mapWithKeys(fn ($s) => [$s->id => $s->code . ' · ' . $s->title])->all()"
                        :value="$class->subject_id" placeholder="Choose a subject" required :disabled="$hasEnrollments" />
                    @if ($hasEnrollments)<input type="hidden" name="subject_id" value="{{ $class->subject_id }}">@endif
                </div>
                <div class="col-md-4">
                    <x-field name="class_code" label="Class code" :value="$class->class_code" required maxlength="20" />
                </div>
                <div class="col-md-4">
                    <x-field name="section" label="Section" :value="$class->section" required maxlength="20" />
                </div>
                <div class="col-md-4">
                    <x-field name="capacity" type="number" label="Capacity (seats)" :value="$class->capacity" required min="1" max="200" />
                </div>
                <div class="col-md-8">
                    <x-select name="teacher_id" label="Teacher"
                        :options="$teachers->pluck('name', 'id')->all()" :value="$class->teacher_id"
                        placeholder="Choose a teacher" required />
                </div>
                <div class="col-md-4">
                    <x-select name="status" label="Status"
                        :options="['open' => 'Open', 'closed' => 'Closed', 'cancelled' => 'Cancelled']"
                        :value="$class->status" required />
                </div>
            </div>
        </div>

        <div class="card-sies p-4 mb-4">
            <h2 class="h6 fw-bold mb-1">Weekly schedule</h2>
            <p class="text-secondary small">Fill one row for each meeting. Leave the day empty to skip a row.</p>

            @foreach ($errors->get('meetings') as $message)
                <div class="alert alert-danger py-2 small">{{ $message }}</div>
            @endforeach
            @foreach ($errors->get('meetings.*') as $messages)
                @foreach ($messages as $message)
                    <div class="alert alert-danger py-2 small">{{ $message }}</div>
                @endforeach
            @endforeach

            <div class="table-responsive">
                <table class="table table-sies align-middle">
                    <thead><tr><th>Day</th><th>Start</th><th>End</th><th>Room</th></tr></thead>
                    <tbody>
                        @foreach ($meetingRows as $i => $row)
                            <tr>
                                <td>
                                    <select name="meetings[{{ $i }}][day]" class="form-select" aria-label="Day {{ $i + 1 }}">
                                        <option value="">—</option>
                                        @foreach ($days as $number => $label)
                                            <option value="{{ $number }}" @selected((string) old("meetings.$i.day", $row['day']) === (string) $number)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input type="time" name="meetings[{{ $i }}][start]" value="{{ old("meetings.$i.start", $row['start']) }}" class="form-control" aria-label="Start time {{ $i + 1 }}"></td>
                                <td><input type="time" name="meetings[{{ $i }}][end]" value="{{ old("meetings.$i.end", $row['end']) }}" class="form-control" aria-label="End time {{ $i + 1 }}"></td>
                                <td><input type="text" name="meetings[{{ $i }}][room]" value="{{ old("meetings.$i.room", $row['room']) }}" class="form-control" maxlength="50" placeholder="e.g. Room 101" aria-label="Room {{ $i + 1 }}"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-navy px-4">{{ $editing ? 'Save changes' : 'Create class' }}</button>
            <a href="{{ $editing ? route('classes.show', $class) : route('classes.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
        </div>
    </form>
</x-app-layout>
