<?php

// Only pages that really exist are listed here. New pages are added as each stage is built.
// Change Password and Logout live in the account menu (top right) and at the foot of the sidebar, not in this list.
// 'icon' is a name from resources/views/components/icon.blade.php; 'group' is the small heading above a run of items.

return [
    'admin' => [
        'workspace' => 'Admin Workspace',
        'role_label' => 'Administrator',
        'items' => [
            ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'active' => 'admin.dashboard', 'icon' => 'dashboard', 'group' => 'Overview'],
            ['label' => 'Student Records', 'route' => 'students.index', 'active' => 'students.*', 'icon' => 'students', 'group' => 'People'],
            ['label' => 'Enrollment Requests', 'route' => 'enrollments.index', 'active' => 'enrollments.*', 'icon' => 'clipboard', 'group' => 'People', 'badge' => 'pending_enrollments'],
            ['label' => 'Class Management', 'route' => 'classes.index', 'active' => ['classes.*'], 'icon' => 'classes', 'group' => 'Academics'],
            ['label' => 'Subject Management', 'route' => 'subjects.index', 'active' => 'subjects.*', 'icon' => 'book', 'group' => 'Academics'],
            ['label' => 'Programs', 'route' => 'programs.index', 'active' => 'programs.*', 'icon' => 'layers', 'group' => 'Academics'],
            ['label' => 'Academic Years & Terms', 'route' => 'academic-years.index', 'active' => ['academic-years.*', 'terms.*'], 'icon' => 'calendar', 'group' => 'Academics'],
            ['label' => 'Holidays', 'route' => 'holidays.index', 'active' => 'holidays.*', 'icon' => 'flag', 'group' => 'Academics'],
            ['label' => 'Attendance Monitoring', 'route' => 'attendance.index', 'active' => 'attendance.*', 'icon' => 'check', 'group' => 'Attendance'],
            ['label' => 'Reports', 'route' => 'reports.index', 'active' => 'reports.*', 'icon' => 'chart', 'group' => 'Attendance'],
            ['label' => 'User Management', 'route' => 'users.index', 'active' => 'users.*', 'icon' => 'user', 'group' => 'System'],
            ['label' => 'Audit Logs', 'route' => 'audit-logs.index', 'active' => 'audit-logs.*', 'icon' => 'file', 'group' => 'System'],
            ['label' => 'System Settings', 'route' => 'settings.edit', 'active' => 'settings.*', 'icon' => 'sliders', 'group' => 'System'],
        ],
    ],
    'registrar' => [
        'workspace' => 'Registrar Workspace',
        'role_label' => 'Registrar',
        'items' => [
            ['label' => 'Dashboard', 'route' => 'registrar.dashboard', 'active' => 'registrar.dashboard', 'icon' => 'dashboard', 'group' => 'Overview'],
            ['label' => 'Student Records', 'route' => 'students.index', 'active' => 'students.*', 'icon' => 'students', 'group' => 'People'],
            ['label' => 'Enrollment Requests', 'route' => 'enrollments.index', 'active' => 'enrollments.*', 'icon' => 'clipboard', 'group' => 'People', 'badge' => 'pending_enrollments'],
            ['label' => 'Class Management', 'route' => 'classes.index', 'active' => ['classes.*'], 'icon' => 'classes', 'group' => 'Academics'],
            ['label' => 'Subject Management', 'route' => 'subjects.index', 'active' => 'subjects.*', 'icon' => 'book', 'group' => 'Academics'],
            ['label' => 'Attendance Monitoring', 'route' => 'attendance.index', 'active' => 'attendance.*', 'icon' => 'check', 'group' => 'Attendance'],
            ['label' => 'Reports', 'route' => 'reports.index', 'active' => 'reports.*', 'icon' => 'chart', 'group' => 'Attendance'],
            ['label' => 'My Profile', 'route' => 'profile.show', 'active' => 'profile.*', 'icon' => 'user', 'group' => 'Me'],
        ],
    ],
    'teacher' => [
        'workspace' => 'Teacher Workspace',
        'role_label' => 'Teacher',
        'items' => [
            ['label' => 'Dashboard', 'route' => 'teacher.dashboard', 'active' => 'teacher.dashboard', 'icon' => 'dashboard', 'group' => 'Overview'],
            ['label' => 'My Classes', 'route' => 'teacher.classes', 'active' => ['teacher.classes', 'classes.show'], 'icon' => 'classes', 'group' => 'Teaching'],
            ['label' => 'Attendance', 'route' => 'attendance.index', 'active' => 'attendance.*', 'icon' => 'check', 'group' => 'Teaching'],
            ['label' => 'Reports', 'route' => 'reports.index', 'active' => 'reports.*', 'icon' => 'chart', 'group' => 'Teaching'],
            ['label' => 'My Profile', 'route' => 'profile.show', 'active' => 'profile.*', 'icon' => 'user', 'group' => 'Me'],
        ],
    ],
    'student' => [
        'workspace' => 'Student Workspace',
        'role_label' => 'Student',
        'items' => [
            ['label' => 'Dashboard', 'route' => 'student.dashboard', 'active' => 'student.dashboard', 'icon' => 'dashboard', 'group' => 'Overview'],
            ['label' => 'My Profile', 'route' => 'student.profile', 'active' => 'student.profile', 'icon' => 'user', 'group' => 'Me'],
            ['label' => 'Available Courses', 'route' => 'student.courses', 'active' => 'student.courses', 'icon' => 'book', 'group' => 'Me'],
            ['label' => 'My Enrollments', 'route' => 'student.enrollments', 'active' => 'student.enrollments', 'icon' => 'clipboard', 'group' => 'Me'],
            ['label' => 'My Schedule', 'route' => 'student.schedule', 'active' => 'student.schedule', 'icon' => 'calendar', 'group' => 'Me'],
            ['label' => 'My Attendance', 'route' => 'student.attendance', 'active' => 'student.attendance*', 'icon' => 'check', 'group' => 'Me'],
        ],
    ],
];
