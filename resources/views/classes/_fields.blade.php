{{-- Class fields, shared by the Add/Edit page and the Add Class dialog on the class list.
     Needs: $class, $terms, $subjects, $programs, $teachers, $days, $meetingRows, $hasEnrollments. --}}
@php
    $editing = $class->exists;
    // Inside the page dialog the frame is narrower than the window, so go two-column sooner.
    $wide = request()->boolean('embed') ? 'lg' : 'xl';
@endphp
{{-- Two columns on wide screens (details | weekly schedule) so the whole form fits one screen. --}}
<div class="row g-3 align-items-start">
    <div class="col-{{ $wide }}-7">
        <section class="card-sies form-pane" aria-labelledby="details-heading">
            <div class="form-pane-head">
                <h2 class="section-title" id="details-heading">Class details</h2>
                <span class="schedule-code-chip" title="Year + term + number. Students use it to find the class.">
                    Schedule code
                    <strong>{{ $editing ? $class->class_code : 'given when you save' }}</strong>
                </span>
            </div>

            @if ($hasEnrollments)
                <div class="alert alert-info small py-2">Students have requested this class, so its term and subject cannot change.</div>
            @endif

            <div class="row">
                <div class="col-md-5">
                    <x-select name="term_id" label="Term"
                        :options="$terms->mapWithKeys(fn ($t) => [$t->id => $t->name . ' · ' . $t->academicYear->name])->all()"
                        :value="$class->term_id" placeholder="Choose a term" required :disabled="$hasEnrollments" />
                    @if ($hasEnrollments)<input type="hidden" name="term_id" value="{{ $class->term_id }}">@endif
                </div>
                <div class="col-md-7">
                    <x-select name="subject_id" label="Subject"
                        :options="$subjects->mapWithKeys(fn ($s) => [$s->id => $s->code . ' · ' . $s->title . ' (' . $s->programsLabel() . ')'])->all()"
                        :value="$class->subject_id" placeholder="Choose a subject" required :disabled="$hasEnrollments" />
                    @if ($hasEnrollments)<input type="hidden" name="subject_id" value="{{ $class->subject_id }}">@endif
                </div>
            </div>

            {{-- The block the class is for. Leave the program empty for a class open to any block (irregular students). --}}
            <div class="block-picker mb-3">
                <div class="block-picker-title">
                    Block <span class="block-preview" data-block-preview>{{ $editing ? $class->block_label : '' }}</span>
                    <span class="block-picker-hint">BSCS 1-C and BSIT 1-C are different blocks.</span>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <x-select name="program_id" label="Program" :options="$programs->mapWithKeys(fn ($p) => [$p->id => $p->code . ' · ' . $p->name])->all()"
                            :value="$class->program_id" placeholder="Any block (irregular students)" data-block-part="program" />
                    </div>
                    <div class="col-md-3 col-6">
                        <x-select name="year_level" label="Year level" :options="array_combine(range(1, 6), array_map(fn ($n) => 'Year ' . $n, range(1, 6)))"
                            :value="$class->year_level" placeholder="—" data-block-part="year" />
                    </div>
                    <div class="col-md-3 col-6">
                        <x-field name="section" label="Section" :value="$class->section" required maxlength="10" placeholder="e.g. C" data-block-part="section" />
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <x-select name="teacher_id" label="Teacher"
                        :options="$teachers->pluck('name', 'id')->all()" :value="$class->teacher_id"
                        placeholder="Choose a teacher" required />
                </div>
                <div class="col-md-3 col-6">
                    <x-select name="status" label="Status"
                        :options="['open' => 'Open', 'closed' => 'Closed', 'cancelled' => 'Cancelled']"
                        :value="$class->status" required />
                </div>
                <div class="col-md-3 col-6">
                    <x-field name="capacity" type="number" label="Seats" :value="$class->capacity" required min="1" max="200" />
                </div>
            </div>
        </section>
    </div>

    <div class="col-{{ $wide }}-5">
        <section class="card-sies form-pane" aria-labelledby="schedule-heading">
            <div class="form-pane-head">
                <h2 class="section-title" id="schedule-heading">Weekly schedule</h2>
            </div>
            <p class="form-pane-help">One row per meeting. Leave the day empty to skip a row.</p>

            @foreach ($errors->get('meetings') as $message)
                <div class="alert alert-danger py-2 small">{{ $message }}</div>
            @endforeach
            @foreach ($errors->get('meetings.*') as $messages)
                @foreach ($messages as $message)
                    <div class="alert alert-danger py-2 small">{{ $message }}</div>
                @endforeach
            @endforeach

            <div class="meeting-rows">
                <div class="meeting-row meeting-row-head" aria-hidden="true"><span>Day</span><span>Start</span><span>End</span><span>Room</span></div>
                @foreach ($meetingRows as $i => $row)
                    <div class="meeting-row">
                        <select name="meetings[{{ $i }}][day]" class="form-select" aria-label="Day {{ $i + 1 }}">
                            <option value="">—</option>
                            @foreach ($days as $number => $label)
                                <option value="{{ $number }}" @selected((string) old("meetings.$i.day", $row['day']) === (string) $number)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <input type="time" name="meetings[{{ $i }}][start]" value="{{ old("meetings.$i.start", $row['start']) }}" class="form-control" aria-label="Start time {{ $i + 1 }}">
                        <input type="time" name="meetings[{{ $i }}][end]" value="{{ old("meetings.$i.end", $row['end']) }}" class="form-control" aria-label="End time {{ $i + 1 }}">
                        <input type="text" name="meetings[{{ $i }}][room]" value="{{ old("meetings.$i.room", $row['room']) }}" class="form-control" maxlength="50" placeholder="Room" aria-label="Room {{ $i + 1 }}">
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</div>
