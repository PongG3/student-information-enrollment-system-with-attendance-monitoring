@php
    use App\Services\AttendanceStats;
    use App\Support\SessionTimeout;

    // Starting point for the attendance preview (a sample student, 20 meetings).
    $sample = ['present' => 14, 'late' => 3, 'absent' => 2, 'excused' => 1];
    $rules = [
        'late' => old('late_counts_as_attended', $values['late_counts_as_attended']) === '1',
        'excused' => old('excused_in_percentage', $values['excused_in_percentage']),
        'threshold' => (int) old('absentee_threshold_percent', $values['absentee_threshold_percent']),
    ];
    $percentage = AttendanceStats::percentage($sample, $rules);
    $absence = AttendanceStats::absenceRate($sample, $rules);
    $flagged = AttendanceStats::isFlagged($sample, $rules);
    $window = (int) old('attendance_edit_window_days', $values['attendance_edit_window_days']);
@endphp
<x-app-layout title="System Settings" subtitle="Rules used across enrollment, attendance, reports and sign-in.">
    @if ($placeholderName)
        <div class="alert alert-warning d-flex gap-2 align-items-start" role="alert">
            <x-icon name="alert" size="18" />
            <div><strong>Set the real school name.</strong> Reports and the sign-in page still say "{{ $values['school_name'] ?: 'School Name' }}".</div>
        </div>
    @endif

    <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" novalidate
          id="settingsForm" data-settings-form>
        @csrf
        @method('PUT')

        <div class="row g-4">
            <div class="col-xl-8">
                {{-- School --}}
                <section class="card-sies p-4 mb-4" aria-labelledby="school-heading">
                    <div class="settings-section-head">
                        <span class="settings-section-icon"><x-icon name="book" size="18" /></span>
                        <div>
                            <h2 class="h6 fw-bold mb-0" id="school-heading">School</h2>
                            <p class="small text-secondary mb-0">Shown on the sign-in page and on every printed or PDF report.</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-8">
                            <x-field name="school_name" label="School name" :value="$values['school_name']" required maxlength="150" />
                            <x-field name="school_address" label="Address (optional)" :value="$values['school_address']" maxlength="255"
                                placeholder="e.g. 123 Rizal Street, Quezon City" help="Printed under the school name on reports." />
                            <x-field name="registrar_contact" label="Registrar contact (optional)" :value="$values['registrar_contact']" maxlength="150"
                                help="An email address or phone number shown on the sign-in page, for people who need help." />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small" for="logo">Logo (optional)</label>
                            <div class="logo-box mb-2" data-logo-box>
                                <img src="{{ $logoUrl ?? '' }}" alt="Current school logo" data-logo-preview @if (! $logoUrl) hidden @endif>
                                <span class="small text-secondary text-center" data-logo-empty @if ($logoUrl) hidden @endif>No logo yet</span>
                            </div>
                            <input type="file" name="logo" id="logo" accept="image/png,image/jpeg"
                                   class="form-control form-control-sm @error('logo') is-invalid @enderror" data-logo-input aria-describedby="logo-help">
                            <div class="form-text" id="logo-help">PNG or JPG, up to 1 MB. A square image looks best.</div>
                            @error('logo')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            @if ($logoUrl)
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="remove_logo" data-logo-remove>
                                    <label class="form-check-label small" for="remove_logo">Remove the logo</label>
                                </div>
                            @endif
                        </div>
                    </div>
                </section>

                {{-- Enrollment --}}
                <section class="card-sies p-4 mb-4" aria-labelledby="enrollment-heading">
                    <div class="settings-section-head">
                        <span class="settings-section-icon"><x-icon name="clipboard" size="18" /></span>
                        <div>
                            <h2 class="h6 fw-bold mb-0" id="enrollment-heading">Enrollment</h2>
                            <p class="small text-secondary mb-0">Checked every time a request is recorded or approved.</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <x-field name="max_units_per_term" type="number" label="Maximum load per term" :value="$values['max_units_per_term']"
                                required min="1" max="40" suffix="units" help="A student cannot be enrolled in more units than this in one term." />
                        </div>
                    </div>
                </section>

                {{-- Attendance --}}
                <section class="card-sies p-4 mb-4" aria-labelledby="attendance-heading">
                    <div class="settings-section-head">
                        <span class="settings-section-icon"><x-icon name="check" size="18" /></span>
                        <div>
                            <h2 class="h6 fw-bold mb-0" id="attendance-heading">Attendance</h2>
                            <p class="small text-secondary mb-0">How percentages and absence alerts are worked out. Try them in the preview.</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <x-field name="attendance_edit_window_days" type="number" label="Teacher edit window"
                                :value="$values['attendance_edit_window_days']" required min="0" max="60" suffix="days" data-setting="window"
                                help="Teachers may correct their own sheets this many days back. Older sheets need an administrator." />
                        </div>
                        <div class="col-md-6">
                            <x-field name="absentee_threshold_percent" type="number" label="Frequent-absence alert"
                                :value="$values['absentee_threshold_percent']" required min="1" max="100" suffix="%" data-setting="threshold"
                                help="Students absent in more than this share of their class meetings are flagged." />
                        </div>
                        <div class="col-md-6">
                            <x-check name="late_counts_as_attended" label="Late counts as attended" :checked="$values['late_counts_as_attended'] === '1'"
                                help="When on, Present and Late both count toward the attendance percentage." />
                        </div>
                        <div class="col-md-6">
                            <x-select name="excused_in_percentage" label="Excused absences"
                                :options="['excluded' => 'Leave out of the percentage', 'attended' => 'Count as attended']"
                                :value="$values['excused_in_percentage']" required />
                        </div>
                    </div>
                </section>

                {{-- Sign-in security --}}
                <section class="card-sies p-4 mb-4" aria-labelledby="security-heading">
                    <div class="settings-section-head">
                        <span class="settings-section-icon"><x-icon name="key" size="18" /></span>
                        <div>
                            <h2 class="h6 fw-bold mb-0" id="security-heading">Sign-in security</h2>
                            <p class="small text-secondary mb-0">Protects records on shared or unattended computers.</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <x-field :name="SessionTimeout::SETTING" type="number" label="Sign out after being idle for"
                                :value="$values[SessionTimeout::SETTING]" required :min="SessionTimeout::MIN" :max="SessionTimeout::MAX" suffix="minutes"
                                data-setting="session" help="From {{ SessionTimeout::MIN }} minutes to {{ SessionTimeout::MAX / 60 }} hours. 30 to 120 minutes suits shared school computers." />
                        </div>
                    </div>
                </section>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-navy px-4">Save settings</button>
                    <a href="{{ route('settings.edit') }}" class="btn btn-outline-secondary px-4" data-settings-discard>Discard changes</a>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="settings-aside">
                    {{-- Live attendance preview --}}
                    <section class="card-sies p-4 mb-4" aria-labelledby="preview-heading" data-attendance-preview
                             data-min-meetings="{{ AttendanceStats::MIN_MEETINGS_FOR_ALERT }}">
                        <h2 class="h6 fw-bold mb-1" id="preview-heading">Attendance preview</h2>
                        <p class="small text-secondary">A sample student in one class. Change the numbers or the settings to see the result. Nothing here is saved.</p>

                        <div class="preview-counts mb-3">
                            @foreach (['present' => 'Present', 'late' => 'Late', 'absent' => 'Absent', 'excused' => 'Excused'] as $key => $label)
                                <label class="preview-count">
                                    <span class="small text-secondary">{{ $label }}</span>
                                    <input type="number" min="0" max="99" value="{{ $sample[$key] }}" class="form-control form-control-sm"
                                           data-count="{{ $key }}" form="previewOnly" aria-label="{{ $label }} meetings in the sample">
                                </label>
                            @endforeach
                        </div>

                        <div class="preview-result" aria-live="polite">
                            <div class="d-flex align-items-baseline gap-2">
                                <span class="preview-percentage" data-out="percentage">{{ $percentage === null ? '—' : number_format($percentage, 1) . '%' }}</span>
                                <span class="small text-secondary">attendance</span>
                            </div>
                            <div class="small text-secondary mb-2" data-out="formula"></div>
                            <div class="small mb-2">Absence rate: <strong data-out="absence">{{ $absence === null ? '—' : number_format($absence, 1) . '%' }}</strong></div>
                            <div data-out="flag">
                                @if ($flagged)
                                    <span class="badge text-bg-danger">Flagged for frequent absence</span>
                                @else
                                    <span class="badge text-bg-success">Not flagged</span>
                                @endif
                            </div>
                        </div>
                    </section>

                    {{-- What the rules mean today --}}
                    <section class="card-sies p-4 mb-4" aria-labelledby="today-heading">
                        <h2 class="h6 fw-bold mb-3" id="today-heading">What this means today</h2>
                        <ul class="settings-effects small mb-0">
                            <li><x-icon name="calendar" size="16" /><span>Teachers can still correct sheets dated
                                <strong data-out="window-date">{{ today()->subDays($window)->format('M j, Y') }}</strong> or later.</span></li>
                            <li><x-icon name="key" size="16" /><span>Anyone idle for <strong data-out="session">{{ $values[SessionTimeout::SETTING] }} minutes</strong> is signed out.</span></li>
                            <li><x-icon name="flag" size="16" /><span>Alerts start after at least {{ AttendanceStats::MIN_MEETINGS_FOR_ALERT }} recorded meetings.</span></li>
                        </ul>
                    </section>

                    {{-- Last change --}}
                    <section class="card-sies p-4" aria-labelledby="history-heading">
                        <h2 class="h6 fw-bold mb-2" id="history-heading">Last saved</h2>
                        @if ($lastChange)
                            <p class="small mb-1">
                                <strong>{{ $lastChange->user?->name ?? 'Someone' }}</strong>,
                                <span title="{{ $lastChange->created_at->format('M j, Y g:i A') }}">{{ $lastChange->created_at->diffForHumans() }}</span>
                            </p>
                            @if ($lastChangedLabels)
                                <p class="small text-secondary mb-2">Changed: {{ implode(', ', $lastChangedLabels) }}</p>
                            @endif
                            <a href="{{ route('audit-logs.index', ['action' => 'settings.updated']) }}" class="small fw-semibold text-decoration-none">See all changes in the audit log</a>
                        @else
                            <p class="small text-secondary mb-0">No changes recorded yet.</p>
                        @endif
                    </section>
                </div>
            </div>
        </div>

        {{-- Appears once something is changed. --}}
        <div class="settings-savebar" data-settings-savebar hidden>
            <span class="small fw-semibold">You have unsaved changes.</span>
            <div class="d-flex gap-2">
                <a href="{{ route('settings.edit') }}" class="btn btn-outline-light btn-sm-sies px-3" data-settings-discard>Discard</a>
                <button type="submit" class="btn btn-teal btn-sm-sies px-3">Save settings</button>
            </div>
        </div>
    </form>
</x-app-layout>
