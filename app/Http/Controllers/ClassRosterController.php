<?php

namespace App\Http\Controllers;

use App\Models\ClassMeeting;
use App\Models\ClassOffering;
use App\Models\Enrollment;
use App\Services\ScheduleChecker;
use App\Support\AuditLogger;
use App\Support\SchoolBranding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * A printable class list: officially enrolled students with blank columns for signatures and the first meetings,
 * for the first days of class. Staff print any class; a teacher only their own (ClassOfferingPolicy).
 */
class ClassRosterController extends Controller
{
    public function __invoke(Request $request, ClassOffering $classOffering)
    {
        Gate::authorize('view', $classOffering);
        $classOffering->load(['subject', 'teacher', 'term.academicYear', 'meetings', 'program']);

        $students = Enrollment::with('student.program')
            ->where('class_offering_id', $classOffering->id)->where('status', Enrollment::STATUS_ENROLLED)
            ->get()->pluck('student')->filter()
            ->sortBy(fn ($s) => mb_strtolower($s->last_name . ' ' . $s->first_name))->values();

        $meetings = $classOffering->meetings->sortBy(['day_of_week', 'start_time'])->values();

        AuditLogger::log('class.roster_printed', $classOffering, "Printed the class list of {$classOffering->class_code} ({$students->count()} students)");

        return Pdf::loadView('classes.roster-pdf', [
            'class' => $classOffering,
            'students' => $students,
            'schedule' => $meetings->map(fn (ClassMeeting $m) => ClassMeeting::DAYS[$m->day_of_week] . ' ' . ScheduleChecker::time($m->start_time) . '–' . ScheduleChecker::time($m->end_time) . ($m->room ? ' · ' . $m->room : ''))->all(),
            'school' => SchoolBranding::hasRealName() ? SchoolBranding::name() : 'SIES Campus Portal',
            'address' => SchoolBranding::address(),
            'logo' => SchoolBranding::logoDataUri(),
            'generatedAt' => now(),
            'generatedBy' => $request->user()->name,
        ])->setPaper('a4')->download('class-list-' . $classOffering->class_code . '.pdf');
    }
}
