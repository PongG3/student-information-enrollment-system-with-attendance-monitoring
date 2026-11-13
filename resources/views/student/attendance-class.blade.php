@use('App\Services\StudentPortalData', 'Portal')
<x-app-layout :title="'Attendance · ' . $class->subject->code"
    :subtitle="$class->subject->title . ' · ' . $class->class_code . ' · ' . $class->term->name . ' · ' . $class->term->academicYear->name"
    :back="route('student.attendance', ['term' => $class->term_id])" back-label="Back to my attendance">
    <div class="row g-3 mb-4">
        @foreach ([['Attendance', Portal::percent($percentage)], ['Present', $counts['present']], ['Late', $counts['late']], ['Absent', $counts['absent']], ['Excused', $counts['excused']]] as [$label, $value])
            <div class="col-6 col-md-4 col-xl">
                <div class="stat-card"><div class="stat-label">{{ $label }}</div><div class="stat-value">{{ $value }}</div></div>
            </div>
        @endforeach
    </div>

    @error('excuse')<div class="alert alert-danger">{{ $message }}</div>@enderror

    @if ($flagged)
        <div class="alert alert-danger">You have been absent in more than {{ $rules['threshold'] }}% of this class's meetings. Please talk to your teacher.</div>
    @endif

    <div class="card-sies">
        <div class="card-header-sies"><h2 class="h6 fw-bold mb-0">Meetings</h2></div>
        @if ($records->isEmpty())
            <x-empty-state icon="check" message="No attendance has been recorded for you in this class yet." />
        @else
            <div class="table-responsive">
                <table class="table table-sies align-middle mb-0">
                    <thead><tr><th scope="col">Date</th><th scope="col">Status</th><th scope="col">Remarks</th><th scope="col">Excuse letter</th></tr></thead>
                    <tbody>
                        @foreach ($records as $record)
                            <tr>
                                <td>{{ $record->session->session_date->format('D, M j, Y') }}</td>
                                <td><x-status-badge :value="$record->status" /></td>
                                <td class="text-secondary">{{ $record->remarks ?: '—' }}</td>
                                <td>
                                    @php
                                        $day = $record->session->session_date->toDateString();
                                        $excuse = $excuses->get($day);
                                    @endphp
                                    @if ($excuse && $excuse->status !== 'pending')
                                        <x-status-badge :value="$excuse->status === 'accepted' ? 'verified' : 'rejected'" :label="ucfirst($excuse->status)" />
                                        @if ($excuse->decision_note)
                                            <div class="small text-secondary mt-1">Teacher's note: {{ $excuse->decision_note }}</div>
                                        @endif
                                    @elseif (in_array($record->status, ['absent', 'late'], true))
                                        @if ($excuse)<x-status-badge value="pending" label="Sent, waiting" />@endif
                                        <button type="button" class="btn btn-outline-secondary btn-sm-sies ms-1" data-bs-toggle="modal" data-bs-target="#excuseModal"
                                            data-excuse-date="{{ $day }}" data-excuse-label="{{ $record->session->session_date->format('D, M j, Y') }}"
                                            data-excuse-reason="{{ $excuse?->reason }}" data-excuse-others="{{ $otherMissed[$day] ?? '' }}">{{ $excuse ? 'Edit' : 'Explain' }}</button>
                                    @else
                                        <span class="text-secondary">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Send an excuse letter (the teacher decides it on that day's sheet). One letter per class meeting;
         when other classes were missed the same day, it can be sent for those too. --}}
    <div class="modal fade" id="excuseModal" tabindex="-1" aria-labelledby="excuseTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('student.attendance.excuse', $class) }}" enctype="multipart/form-data" class="modal-content" novalidate data-excuse-form>
                @csrf
                <input type="hidden" name="date" value="{{ old('date') }}" data-excuse-field="date">
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title h5 fw-bold" id="excuseTitle">Excuse letter</h2>
                        <div class="small text-secondary"><span data-excuse-field="label">{{ $class->subject->code }}</span> · one letter per class meeting</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label" for="excuseReason">Reason <span class="text-danger">*</span></label>
                    <textarea name="reason" id="excuseReason" rows="3" minlength="10" maxlength="500" required
                        class="form-control excuse-reason @error('reason') is-invalid @enderror" data-excuse-field="reason"
                        aria-describedby="excuseCount" placeholder="For example: I had a fever and saw a doctor.">{{ old('reason') }}</textarea>
                    <div class="d-flex justify-content-between small mt-1">
                        <span class="text-danger">@error('reason'){{ $message }}@enderror</span>
                        <span class="text-secondary" id="excuseCount" data-excuse-count aria-live="polite">0 / 500</span>
                    </div>

                    <label class="form-label mt-3" for="excuseFile">Letter or medical certificate <span class="text-secondary fw-normal">(optional)</span></label>
                    <x-file-drop name="file" id="excuseFile" accept=".pdf,.jpg,.jpeg,.png" hint="PDF or photo, up to 5 MB" :max-mb="5" />

                    {{-- Several days off: one letter for every missed meeting up to this day. --}}
                    <label class="form-label mt-3" for="excuseUntil">Absent for several days? Last day <span class="text-secondary fw-normal">(optional)</span></label>
                    <input type="date" name="until" id="excuseUntil" class="form-control @error('until') is-invalid @enderror" value="{{ old('until') }}"
                           max="{{ today()->toDateString() }}" data-excuse-field="until">
                    @error('until')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">Covers every meeting you missed from the first day to this one.</div>

                    <div class="form-check excuse-all-day mt-3" data-excuse-all-day hidden>
                        <input class="form-check-input" type="checkbox" name="all_day" value="1" id="excuseAllDay" @checked(old('all_day'))>
                        <label class="form-check-label small" for="excuseAllDay">
                            Also send it for my other missed classes that day: <strong data-excuse-field="others"></strong>
                        </label>
                    </div>
                    <p class="small text-secondary mt-3 mb-0">Your teacher sees it on that day's attendance sheet and decides.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-navy px-4" data-excuse-submit disabled>Submit excuse</button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const modal = document.getElementById('excuseModal');
                const form = modal.querySelector('[data-excuse-form]');
                const field = (name) => modal.querySelector(`[data-excuse-field="${name}"]`);
                const reason = field('reason');
                const count = modal.querySelector('[data-excuse-count]');
                const submit = modal.querySelector('[data-excuse-submit]');
                const allDay = modal.querySelector('[data-excuse-all-day]');
                const MIN = 10;

                // Counter, and Submit stays off until the reason is long enough.
                const check = () => {
                    const length = reason.value.trim().length;
                    count.textContent = `${reason.value.length} / 500` + (length > 0 && length < MIN ? ` · at least ${MIN}` : '');
                    submit.disabled = length < MIN;
                };
                reason.addEventListener('input', check);

                modal.addEventListener('show.bs.modal', (event) => {
                    const button = event.relatedTarget;
                    if (!button) { check(); return; }   // reopened after an error: keep what was typed
                    form.reset();
                    field('date').value = button.dataset.excuseDate;
                    field('until').min = button.dataset.excuseDate;   // the last day is on or after the first
                    field('label').textContent = '{{ $class->subject->code }} · ' + button.dataset.excuseLabel;
                    reason.value = button.dataset.excuseReason || '';
                    field('others').textContent = button.dataset.excuseOthers || '';
                    allDay.hidden = !button.dataset.excuseOthers;
                    check();
                });

                @if ($errors->hasAny(['reason', 'file', 'until']) && old('date'))
                    field('others').textContent = @json($otherMissed[old('date')] ?? '');
                    allDay.hidden = !@json(isset($otherMissed[old('date')]));
                    bootstrap.Modal.getOrCreateInstance(modal).show();
                @endif
            });
        </script>
    @endpush
</x-app-layout>
