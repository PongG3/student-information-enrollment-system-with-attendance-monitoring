<?php

return [
    'student' => [
        'workspace' => 'Student Workspace',
        'role_label' => 'Student',
        'items' => [
            ['slug' => 'dashboard', 'label' => 'Dashboard'],
            ['slug' => 'profile', 'label' => 'My Profile'],
            ['slug' => 'courses', 'label' => 'Available Courses'],
            ['slug' => 'enrollments', 'label' => 'My Enrollments'],
            ['slug' => 'schedule', 'label' => 'My Schedule'],
            ['slug' => 'attendance', 'label' => 'My Attendance'],
            ['slug' => 'settings', 'label' => 'Settings'],
        ],
    ],
    'teacher' => [
        'workspace' => 'Teacher Workspace',
        'role_label' => 'Teacher',
        'items' => [
            ['slug' => 'dashboard', 'label' => 'Dashboard'],
            ['slug' => 'classes', 'label' => 'Assigned Classes'],
            ['slug' => 'roster', 'label' => 'Class Roster'],
            ['slug' => 'record-attendance', 'label' => 'Record Attendance'],
            ['slug' => 'attendance-history', 'label' => 'Attendance History'],
            ['slug' => 'reports', 'label' => 'Class Reports'],
            ['slug' => 'settings', 'label' => 'Settings'],
        ],
    ],
    'registrar' => [
        'workspace' => 'Registrar Workspace',
        'role_label' => 'Registrar',
        'items' => [
            ['slug' => 'dashboard', 'label' => 'Dashboard'],
            ['slug' => 'students', 'label' => 'Student Records'],
            ['slug' => 'subjects', 'label' => 'Subject Management'],
            ['slug' => 'classes', 'label' => 'Class Management'],
            ['slug' => 'enrollment-requests', 'label' => 'Enrollment Requests'],
            ['slug' => 'attendance-monitoring', 'label' => 'Attendance Monitoring'],
            ['slug' => 'reports', 'label' => 'Reports'],
            ['slug' => 'settings', 'label' => 'Settings'],
        ],
    ],
    // Not in the Figma design: proposed menu for the Administrator.
    'admin' => [
        'workspace' => 'Admin Workspace',
        'role_label' => 'Administrator',
        'items' => [
            ['slug' => 'dashboard', 'label' => 'Dashboard'],
            ['slug' => 'users', 'label' => 'User Management'],
            ['slug' => 'academic-setup', 'label' => 'Academic Setup'],
            ['slug' => 'students', 'label' => 'Student Records'],
            ['slug' => 'enrollment-requests', 'label' => 'Enrollment Requests'],
            ['slug' => 'attendance-monitoring', 'label' => 'Attendance Monitoring'],
            ['slug' => 'reports', 'label' => 'Reports'],
            ['slug' => 'audit-logs', 'label' => 'Audit Logs'],
            ['slug' => 'settings', 'label' => 'Settings'],
        ],
    ],
];
