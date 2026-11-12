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
                    <thead><tr><th>Date</th><th>Status</th><th>Remarks</th><th>Excuse letter</th></tr></thead>
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
                                    @elseif (in_array($record->status, ['absent', 'late'], true))
                                        @if ($excuse)<x-status-badge value="pending" label="Sent, waiting" />@endif
                                        <button type="button" class="btn btn-outline-secondary btn-sm-sies ms-1" data-bs-toggle="modal" data-bs-target="#excuseModal"
                                            data-excuse-date="{{ $day }}" data-excuse-label="{{ $record->session->session_date->format('D, M j, Y') }}"
                                            data-excuse-reason="{{ $excuse?->reason }}">{{ $excuse ? 'Edit' : 'Explain' }}</button>
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

    {{-- Send an excuse letter for one meeting (the teacher decides it on that day's sheet). --}}
    <div class="modal fade" id="excuseModal" tabindex="-1" aria-labelledby="excuseTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('student.attendance.excuse', $class) }}" enctype="multipart/form-data" class="modal-content" novalidate>
                @csrf
                <input type="hidden" name="date" value="{{ old('date') }}" data-excuse-field="date">
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title h5 fw-bold" id="excuseTitle">Excuse letter</h2>
                        <div class="small text-secondary" data-excuse-field="label">{{ $class->subject->code }}</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label" for="excuseReason">Reason <span class="text-danger">*</span></label>
                    <textarea name="reason" id="excuseReason" rows="3" maxlength="500" class="form-control @error('reason') is-invalid @enderror" data-excuse-field="reason"
                        placeholder="For example: I had a fever and saw a doctor.">{{ old('reason') }}</textarea>
                    @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <label class="form-label mt-3" for="excuseFile">Letter or medical certificate (optional)</label>
                    <input type="file" name="file" id="excuseFile" accept=".pdf,.jpg,.jpeg,.png" class="form-control @error('file') is-invalid @enderror">
                    @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">PDF or photo, up to 5 MB. Your teacher sees it on the attendance sheet.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-navy px-4">Send</button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const modal = document.getElementById('excuseModal');
                const field = (name) => modal.querySelector(`[data-excuse-field="${name}"]`);
                modal.addEventListener('show.bs.modal', (event) => {
                    const button = event.relatedTarget;
                    if (!button) return;   // reopened after an error: keep what was typed
                    field('date').value = button.dataset.excuseDate;
                    field('label').textContent = '{{ $class->subject->code }} · ' + button.dataset.excuseLabel;
                    field('reason').value = button.dataset.excuseReason || '';
                });
                @if ($errors->hasAny(['reason', 'file']) && old('date'))
                    bootstrap.Modal.getOrCreateInstance(modal).show();
                @endif
            });
        </script>
    @endpush
</x-app-layout>
