<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Support\AuditLogger;
use App\Support\SchoolBranding;
use App\Support\SessionTimeout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SettingController extends Controller
{
    private const KEYS = [
        'school_name', 'school_address', 'registrar_contact', 'dpo_name', 'dpo_email', 'attendance_edit_window_days', 'absentee_threshold_percent',
        'max_units_per_term', 'late_counts_as_attended', 'excused_in_percentage',
    ];

    /** Readable names for the "last saved" note and the audit log. */
    public const LABELS = [
        'school_name' => 'School name',
        'school_address' => 'School address',
        'school_logo_path' => 'School logo',
        'registrar_contact' => 'Registrar contact',
        'dpo_name' => 'Data Protection Officer',
        'dpo_email' => 'Data Protection Officer email',
        'max_units_per_term' => 'Maximum units',
        'attendance_edit_window_days' => 'Teacher edit window',
        'absentee_threshold_percent' => 'Absence alert',
        'late_counts_as_attended' => 'Late counts as attended',
        'excused_in_percentage' => 'Excused absences',
        'session_lifetime_minutes' => 'Session timeout',
    ];

    public function edit()
    {
        $values = [];
        foreach (self::KEYS as $key) {
            $values[$key] = Setting::get($key);
        }
        $values[SessionTimeout::SETTING] = (string) SessionTimeout::minutes();

        $lastChange = AuditLog::with('user')->where('action', 'settings.updated')->latest('created_at')->latest('id')->first();

        return view('settings.edit', [
            'values' => $values,
            'logoUrl' => SchoolBranding::logoUrl(),
            'placeholderName' => ! SchoolBranding::hasRealName(),
            'lastChange' => $lastChange,
            'lastChangedLabels' => collect(array_keys($lastChange?->new_values ?? []))
                ->map(fn ($key) => self::LABELS[$key] ?? $key)->all(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'school_name' => ['required', 'string', 'max:150'],
            'school_address' => ['nullable', 'string', 'max:255'],
            'registrar_contact' => ['nullable', 'string', 'max:150'],
            'dpo_name' => ['nullable', 'string', 'max:120'],
            'dpo_email' => ['nullable', 'email', 'max:150'],
            'attendance_edit_window_days' => ['required', 'integer', 'min:0', 'max:60'],
            'absentee_threshold_percent' => ['required', 'integer', 'min:1', 'max:100'],
            'max_units_per_term' => ['required', 'integer', 'min:1', 'max:40'],
            'excused_in_percentage' => ['required', Rule::in(['excluded', 'attended'])],
            SessionTimeout::SETTING => ['sometimes', 'required', 'integer', 'min:' . SessionTimeout::MIN, 'max:' . SessionTimeout::MAX],
            // PNG or JPG only: SVG files can carry scripts.
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:1024', 'dimensions:max_width=2000,max_height=2000'],
            'remove_logo' => ['nullable', 'boolean'],
        ], [
            'logo.mimes' => 'The logo must be a PNG or JPG image.',
            'logo.max' => 'The logo may not be larger than 1 MB.',
            'logo.dimensions' => 'The logo may be at most 2000 × 2000 pixels.',
            SessionTimeout::SETTING . '.min' => 'The session timeout must be at least ' . SessionTimeout::MIN . ' minutes.',
            SessionTimeout::SETTING . '.max' => 'The session timeout may be at most ' . SessionTimeout::MAX . ' minutes (8 hours).',
        ]);
        unset($data['logo'], $data['remove_logo']);
        $data['late_counts_as_attended'] = $request->boolean('late_counts_as_attended') ? '1' : '0';

        // Logo: replace or remove; the old file is deleted only after the new value is saved.
        $oldLogo = Setting::get(SchoolBranding::LOGO_SETTING);
        if ($request->hasFile('logo')) {
            $data[SchoolBranding::LOGO_SETTING] = $request->file('logo')->store('branding', 'local');
        } elseif ($request->boolean('remove_logo')) {
            $data[SchoolBranding::LOGO_SETTING] = '';
        }

        $old = [];
        $new = [];
        foreach ($data as $key => $value) {
            $value = trim((string) $value);
            $current = (string) Setting::get($key, '');
            if ($current !== $value) {
                $old[$key] = $current;
                $new[$key] = $value;
                Setting::put($key, $value);
            }
        }

        if (array_key_exists(SchoolBranding::LOGO_SETTING, $new) && $oldLogo) {
            Storage::disk('local')->delete($oldLogo);
        }

        if ($new !== []) {
            $names = implode(', ', array_map(fn ($key) => self::LABELS[$key] ?? $key, array_keys($new)));
            AuditLogger::log('settings.updated', null, 'Updated system settings: ' . $names, $old, $new);
        }

        $nothing = $new === [];

        return redirect()->route('settings.edit')->with($nothing ? 'info' : 'success', $nothing ? 'Nothing changed.' : 'Settings saved.');
    }

    /** The school logo is public: it appears on the sign-in page before anyone signs in. */
    public function logo()
    {
        $path = SchoolBranding::logoPath();
        abort_unless($path, 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'public, max-age=604800']);
    }
}
