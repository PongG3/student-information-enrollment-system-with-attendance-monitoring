<?php

namespace App\Http\Controllers;

use App\Models\AbsenceExcuse;
use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\Student;
use App\Models\StudentRequirement;
use App\Support\AuditLogger;
use App\Support\SchoolBranding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * Everything the system holds about one student, as one PDF: for a data subject's access request
 * under the Data Privacy Act (RA 10173). The registrar exports it for any student; a student for themself.
 */
class PersonalDataController extends Controller
{
    /** Registrar / administrator: any student (archived ones too). */
    public function staff(Request $request, Student $student)
    {
        return $this->export($request, $student);
    }

    /** A student: their own record only. */
    public function mine(Request $request)
    {
        $student = $request->attributes->get('student') ?? Student::where('user_id', $request->user()->id)->firstOrFail();

        return $this->export($request, $student);
    }

    private function export(Request $request, Student $student)
    {
        $student->load(['program', 'guardians', 'user']);

        $data = [
            'student' => $student,
            'school' => SchoolBranding::hasRealName() ? SchoolBranding::name() : 'SIES Campus Portal',
            'generatedAt' => now(),
            'generatedBy' => $request->user()->name,
            'requirements' => StudentRequirement::with('requirement')->where('student_id', $student->id)->get(),
            'enrollments' => $student->enrollments()->with(['classOffering.subject', 'classOffering.term.academicYear'])->orderBy('requested_at')->get(),
            'attendance' => AttendanceRecord::with('session.classOffering.subject')->where('student_id', $student->id)->get()
                ->sortBy(fn ($r) => $r->session->session_date)->values(),
            'excuses' => AbsenceExcuse::with('classOffering.subject')->where('student_id', $student->id)->orderBy('date')->get(),
            // Changes made to this student's record (who, what, when).
            'history' => AuditLog::with('user')->where('subject_type', 'Student')->where('subject_id', $student->id)
                ->latest('created_at')->limit(200)->get(),
        ];

        AuditLogger::log('student.data_exported', $student, "Personal data of {$student->full_name} ({$student->student_number}) exported by {$request->user()->name}");

        return Pdf::loadView('privacy.personal-data', $data)->setPaper('a4')
            ->download('personal-data-' . $student->student_number . '.pdf');
    }
}
