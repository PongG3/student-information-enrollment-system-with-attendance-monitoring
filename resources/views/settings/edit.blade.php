<x-app-layout title="System Settings" subtitle="Rules used across enrollment, attendance, and reports.">
    <div class="card-sies p-4" style="max-width: 640px">
        <form method="POST" action="{{ route('settings.update') }}" novalidate>
            @csrf
            @method('PUT')

            <x-field name="school_name" label="School name" :value="$values['school_name']" required maxlength="150"
                help="Printed on reports." />
            <x-field name="registrar_contact" label="Registrar contact (optional)" :value="$values['registrar_contact']" maxlength="150"
                help="An email address or phone number shown on the sign-in page, for people who need help." />

            <h2 class="h6 fw-bold mt-4">Enrollment</h2>
            <x-field name="max_units_per_term" type="number" label="Maximum units per term" :value="$values['max_units_per_term']"
                required min="1" max="40" help="A student cannot be enrolled in more units than this in one term." />

            <h2 class="h6 fw-bold mt-4">Attendance</h2>
            <x-field name="attendance_edit_window_days" type="number" label="Teacher edit window (days)"
                :value="$values['attendance_edit_window_days']" required min="0" max="60"
                help="Teachers may correct their own attendance records this many days back. Older records need an administrator." />
            <x-field name="absentee_threshold_percent" type="number" label="Frequent-absence alert (%)"
                :value="$values['absentee_threshold_percent']" required min="1" max="100"
                help="Students absent in more than this share of class meetings are flagged." />
            <x-check name="late_counts_as_attended" label="Late counts as attended" :checked="$values['late_counts_as_attended'] === '1'"
                help="When on, Present and Late both count toward the attendance percentage." />
            <x-select name="excused_in_percentage" label="Excused absences in the percentage"
                :options="['excluded' => 'Leave out of the percentage', 'attended' => 'Count as attended']"
                :value="$values['excused_in_percentage']" required />

            <button type="submit" class="btn btn-navy px-4 mt-2">Save settings</button>
        </form>
    </div>
</x-app-layout>
