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

    // Tabs keep each part on one screen. Field lists decide the red error dot and which tab opens after a failed save.
    $tabs = [
        'school' => ['School', 'book', ['school_name', 'school_address', 'registrar_contact', 'dpo_name', 'dpo_email', 'logo', 'remove_logo']],
        'rules' => ['Enrollment & attendance', 'check', ['max_units_per_term', 'attendance_edit_window_days', 'absentee_threshold_percent', 'late_counts_as_attended', 'excused_in_percentage']],
        'security' => ['Sign-in security', 'key', [SessionTimeout::SETTING]],
    ];
    $tabHasErrors = fn (array $fields) => collect($fields)->contains(fn ($f) => $errors->has($f));
    $openTab = collect($tabs)->search(fn ($tab) => $tabHasErrors($tab[2])) ?: 'school';
@endphp
<x-app-layout title="System Settings" subtitle="Rules used across enrollment, attendance, reports and sign-in.">
    <x-slot:actions>
        <div class="settings-last-saved small text-secondary text-md-end">
            @if ($lastChange)
                Last saved by <strong class="text-navy">{{ $lastChange->user?->name ?? 'Someone' }}</strong>,
                <span title="{{ $lastChange->created_at->format('M j, Y g:i A') }}">{{ $lastChange->created_at->diffForHumans() }}</span>
                @if ($lastChangedLabels)<span class="d-block">Changed: {{ implode(', ', $lastChangedLabels) }}</span>@endif
                <a href="{{ route('audit-logs.index', ['action' => 'settings.updated']) }}" class="fw-semibold">See all changes</a>
            @else
                No changes recorded yet.
            @endif
        </div>
    </x-slot:actions>

    @if ($placeholderName)
        <div class="alert alert-warning d-flex gap-2 align-items-start py-2" role="alert">
            <x-icon name="alert" size="18" />
            <div><strong>Set the real school name.</strong> Reports and the sign-in page still say "{{ $values['school_name'] ?: 'School Name' }}".</div>
        </div>
    @endif

    <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" novalidate
          id="settingsForm" data-settings-form>
        @csrf
        @method('PUT')

        <ul class="form-tabs" role="tablist" aria-label="Settings">
            @foreach ($tabs as $key => [$label, $icon, $fields])
                <li role="presentation">
                    <button type="button" role="tab" id="tab-{{ $key }}" data-bs-toggle="tab" data-bs-target="#pane-{{ $key }}"
                        aria-controls="pane-{{ $key }}" aria-selected="{{ $openTab === $key ? 'true' : 'false' }}"
                        @class(['form-tab', 'active' => $openTab === $key])>
                        <x-icon :name="$icon" :size="16" /> <span>{{ $label }}</span>
                        @if ($tabHasErrors($fields))<span class="form-tab-error" title="Has errors"><span class="visually-hidden">(has errors)</span></span>@endif
                    </button>
                </li>
            @endforeach
        </ul>

        <div class="tab-content">
            {{-- School --}}
            <div @class(['tab-pane', 'fade', 'show active' => $openTab === 'school']) id="pane-school" role="tabpanel" aria-labelledby="tab-school" tabindex="0">
                <section class="card-sies form-pane">
                    <p class="form-pane-help">Shown on the sign-in page and on every printed or PDF report.</p>
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="row">
                                <div class="col-md-6"><x-field name="school_name" label="School name" :value="$values['school_name']" required maxlength="150" /></div>
                                <div class="col-md-6"><x-field name="registrar_contact" label="Registrar contact (optional)" :value="$values['registrar_contact']" maxlength="150"
                                    placeholder="Email or phone" help="Shown on the sign-in page for people who need help." /></div>
                                <div class="col-12"><x-field name="school_address" label="Address (optional)" :value="$values['school_address']" maxlength="255"
                                    placeholder="e.g. 123 Rizal Street, Quezon City" help="Printed under the school name on reports." /></div>
                                <div class="col-md-6"><x-field name="dpo_name" label="Data Protection Officer" :value="$values['dpo_name']" maxlength="120"
                                    placeholder="Full name" help="Named in the privacy notice (Data Privacy Act)." /></div>
                                <div class="col-md-6"><x-field name="dpo_email" label="DPO email" type="email" :value="$values['dpo_email']" maxlength="150"
                                    placeholder="dpo@school.edu.ph" help="Where people send privacy requests." /></div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label" for="logo">Logo (optional)</label>
                            <div class="logo-box mb-2" data-logo-box>
                                <img src="{{ $logoUrl ?? '' }}" alt="Current school logo" data-logo-preview @if (! $logoUrl) hidden @endif>
                                <span class="small text-secondary text-center" data-logo-empty @if ($logoUrl) hidden @endif>No logo yet</span>
                            </div>
                            <input type="file" name="logo" id="logo" accept="image/png,image/jpeg"
                                   class="form-control form-control-sm @error('logo') is-invalid @enderror" data-logo-input aria-describedby="logo-help">
                            <div class="form-text" id="logo-help">PNG or JPG, up to 1 MB. A square image looks best.</div>
                            @error('logo')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            @if ($logoUrl)
                                <div class="form-check mt-1">
                                    <input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="remove_logo" data-logo-remove>
                                    <label class="form-check-label small" for="remove_logo">Remove the logo</label>
                                </div>
                            @endif
                        </div>
                    </div>
                </section>
            </div>

            {{-- Enrollment & attendance, with the live preview beside the rules --}}
            <div @class(['tab-pane', 'fade', 'show active' => $openTab === 'rules']) id="pane-rules" role="tabpanel" aria-labelledby="tab-rules" tabindex="0">
                <section class="card-sies form-pane">
                    <div class="row g-4">
                        <div class="col-lg-7">
                            <div class="row">
                                <div class="col-md-6">
                                    <x-field name="max_units_per_term" type="number" label="Maximum load per term" :value="$values['max_units_per_term']"
                                        required min="1" max="40" suffix="units" help="Enrollment: no student may hold more in a term." />
                                </div>
                                <div class="col-md-6">
                                    <x-field name="attendance_edit_window_days" type="number" label="Teacher edit window"
                                        :value="$values['attendance_edit_window_days']" required min="0" max="60" suffix="days" data-setting="window"
                                        help="Older sheets need an administrator." />
                                </div>
                                <div class="col-md-6">
                                    <x-field name="absentee_threshold_percent" type="number" label="Frequent-absence alert"
                                        :value="$values['absentee_threshold_percent']" required min="1" max="100" suffix="%" data-setting="threshold"
                                        help="Flagged after at least {{ AttendanceStats::MIN_MEETINGS_FOR_ALERT }} meetings." />
                                </div>
                                <div class="col-md-6">
                                    <x-select name="excused_in_percentage" label="Excused absences"
                                        :options="['excluded' => 'Leave out of the percentage', 'attended' => 'Count as attended']"
                                        :value="$values['excused_in_percentage']" required />
                                </div>
                                <div class="col-md-6 d-flex align-items-center">
                                    <x-check name="late_counts_as_attended" label="Late counts as attended" :checked="$values['late_counts_as_attended'] === '1'" />
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-5">
                            <div class="settings-preview" data-attendance-preview data-min-meetings="{{ AttendanceStats::MIN_MEETINGS_FOR_ALERT }}">
                                <h2 class="section-title mb-2" title="A sample student. Change the numbers or the rules; nothing here is saved.">Attendance preview <span class="small text-secondary fw-normal">· sample student, not saved</span></h2>
                                <div class="preview-counts mb-2">
                                    @foreach (['present' => 'Present', 'late' => 'Late', 'absent' => 'Absent', 'excused' => 'Excused'] as $key => $label)
                                        <label class="preview-count">
                                            <span class="small text-secondary">{{ $label }}</span>
                                            <input type="number" min="0" max="99" value="{{ $sample[$key] }}" class="form-control form-control-sm"
                                                   data-count="{{ $key }}" form="previewOnly" aria-label="{{ $label }} meetings in the sample">
                                        </label>
                                    @endforeach
                                </div>
                                <div class="preview-result" aria-live="polite">
                                    <div class="d-flex flex-wrap align-items-baseline justify-content-between gap-2">
                                        <span><span class="preview-percentage" data-out="percentage">{{ $percentage === null ? '—' : number_format($percentage, 1) . '%' }}</span> <span class="small text-secondary">attendance</span></span>
                                        <span data-out="flag">
                                            @if ($flagged)<span class="badge text-bg-danger">Flagged for frequent absence</span>@else<span class="badge text-bg-success">Not flagged</span>@endif
                                        </span>
                                    </div>
                                    <div class="small text-secondary" data-out="formula"></div>
                                    <div class="small">Absence rate: <strong data-out="absence">{{ $absence === null ? '—' : number_format($absence, 1) . '%' }}</strong></div>
                                </div>
                                <ul class="settings-effects small mt-2 mb-0" aria-label="What this means today">
                                    <li><x-icon name="calendar" size="16" /><span><span class="visually-hidden">What this means today: </span>Teachers can still correct sheets dated
                                        <strong data-out="window-date">{{ today()->subDays($window)->format('M j, Y') }}</strong> or later.</span></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            {{-- Sign-in security --}}
            <div @class(['tab-pane', 'fade', 'show active' => $openTab === 'security']) id="pane-security" role="tabpanel" aria-labelledby="tab-security" tabindex="0">
                <section class="card-sies form-pane">
                    <p class="form-pane-help">Protects records on shared or unattended computers.</p>
                    <div class="row">
                        <div class="col-md-6 col-lg-4">
                            <x-field :name="SessionTimeout::SETTING" type="number" label="Sign out after being idle for"
                                :value="$values[SessionTimeout::SETTING]" required :min="SessionTimeout::MIN" :max="SessionTimeout::MAX" suffix="minutes"
                                data-setting="session" help="From {{ SessionTimeout::MIN }} minutes to {{ SessionTimeout::MAX / 60 }} hours. 30 to 120 minutes suits shared school computers." />
                        </div>
                        <div class="col-md-6 col-lg-8">
                            <ul class="settings-effects small mt-md-4">
                                <li><x-icon name="key" size="16" /><span>Anyone idle for <strong data-out="session">{{ $values[SessionTimeout::SETTING] }} minutes</strong> is signed out and sees a "Session timed out" notice.</span></li>
                            </ul>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        {{-- Always in view: says whether anything is unsaved. --}}
        <div class="form-actionbar is-sticky mt-3 settings-savebar-sies" data-settings-savebar>
            <span class="small" data-savebar-status>
                <span class="text-secondary" data-saved>All changes saved.</span>
                <span class="fw-semibold text-navy" data-unsaved hidden>You have unsaved changes.</span>
            </span>
            <div class="form-actions ms-sm-auto">
                <a href="{{ route('settings.edit') }}" class="btn btn-outline-secondary px-4" data-settings-discard hidden>Discard</a>
                <button type="submit" class="btn btn-navy px-4">Save settings</button>
            </div>
        </div>
    </form>
</x-app-layout>
