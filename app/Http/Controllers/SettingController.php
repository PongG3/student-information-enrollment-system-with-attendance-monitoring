<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingController extends Controller
{
    private const KEYS = [
        'school_name', 'registrar_contact', 'attendance_edit_window_days', 'absentee_threshold_percent',
        'max_units_per_term', 'late_counts_as_attended', 'excused_in_percentage',
    ];

    public function edit()
    {
        $values = [];
        foreach (self::KEYS as $key) {
            $values[$key] = Setting::get($key);
        }

        return view('settings.edit', compact('values'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'school_name' => ['required', 'string', 'max:150'],
            'registrar_contact' => ['nullable', 'string', 'max:150'],
            'attendance_edit_window_days' => ['required', 'integer', 'min:0', 'max:60'],
            'absentee_threshold_percent' => ['required', 'integer', 'min:1', 'max:100'],
            'max_units_per_term' => ['required', 'integer', 'min:1', 'max:40'],
            'excused_in_percentage' => ['required', Rule::in(['excluded', 'attended'])],
        ]);
        $data['late_counts_as_attended'] = $request->boolean('late_counts_as_attended') ? '1' : '0';

        $old = [];
        $new = [];
        foreach ($data as $key => $value) {
            $value = (string) $value;
            $current = (string) Setting::get($key, '');
            if ($current !== $value) {
                $old[$key] = $current;
                $new[$key] = $value;
                Setting::put($key, $value);
            }
        }

        if ($new !== []) {
            AuditLogger::log('settings.updated', null, 'Updated system settings: ' . implode(', ', array_keys($new)), $old, $new);
        }

        return redirect()->route('settings.edit')->with('success', 'Settings saved.');
    }
}
