<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ClassOffering;
use App\Models\Enrollment;
use App\Models\Term;
use App\Services\AttendanceStats;
use App\Services\DashboardAttention;
use App\Services\DashboardStats;
use App\Services\DashboardWeek;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    /** Audit entries left out of the admin's "Recent activity": routine, not administrative work. */
    private const ROUTINE_ACTIONS = ['login', 'logout', 'login.failed', 'password.changed', 'student.exported', 'report.exported'];

    public function __invoke(Request $request)
    {
        $user = $request->user();
        $first = Str::before($user->name, ' ');

        $hour = (int) now()->format('G');
        $when = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

        $greeting = match ($user->role) {
            'teacher' => "{$when}, {$first}!",
            'registrar' => "{$when}, Registrar!",
            default => "{$when}, Administrator!",
        };

        $term = Term::with('academicYear')->where('is_current', true)->first();
        $isTeacher = $user->role === 'teacher';
        $teacherId = $isTeacher ? $user->id : null;

        $data = [
            'greeting' => $greeting,
            'term' => $term,
            'isTeacher' => $isTeacher,
            'today' => DashboardStats::today($term, $teacherId),
        ];

        // The administrator's dashboard has no charts (they are in Reports), so their data is not loaded.
        if ($user->role === 'admin') {
            $data['overview'] = DashboardStats::overview($term);
            $data['week'] = DashboardWeek::changes($term);
            $data['attention'] = DashboardAttention::items($user, $term, $data['today'], $data['overview']);
            $data['accounts'] = DashboardStats::accounts();
            $data['recent'] = AuditLog::with('user')
                ->whereNotIn('action', self::ROUTINE_ACTIONS)
                ->latest('created_at')->latest('id')->limit(5)->get();

            return view('dashboard.admin', $data);
        }

        $termCounts = DashboardStats::termCounts($term, $teacherId);
        $data['attendanceTrend'] = DashboardStats::attendanceTrend($term, $teacherId);
        $data['termCounts'] = $termCounts;
        $data['termPercentage'] = AttendanceStats::percentage($termCounts);

        if ($isTeacher) {
            $classIds = $term
                ? ClassOffering::where('teacher_id', $user->id)->where('term_id', $term->id)->where('status', '!=', ClassOffering::STATUS_CANCELLED)->pluck('id')
                : collect();

            $data['teacherCards'] = [
                'classes' => $classIds->count(),
                'students' => Enrollment::whereIn('class_offering_id', $classIds)->where('status', Enrollment::STATUS_ENROLLED)->distinct()->count('student_id'),
            ];
            $data['flagged'] = $term ? AttendanceStats::flagged($term, $user->id, null, 5) : collect();
            $data['nextClass'] = DashboardStats::nextClass($term, $user->id);
            $data['byClass'] = DashboardStats::attendanceByClass($term, $user->id);
            $data['attention'] = DashboardAttention::items($user, $term, $data['today'], null);

            return view('dashboard', $data);
        }

        $data['overview'] = DashboardStats::overview($term);
        $data['week'] = DashboardWeek::changes($term);
        $data['attention'] = DashboardAttention::items($user, $term, $data['today'], $data['overview']);
        $data['enrollmentTrend'] = DashboardStats::enrollmentTrend($term);
        // Students over the absence limit, across all classes: the list to follow up.
        $data['flagged'] = $term ? AttendanceStats::flagged($term, null, null, 6) : collect();
        // One line per student, day and status (a student enrolled in four classes is one line, not four).
        $data['recentRequests'] = Enrollment::with(['student', 'classOffering.subject'])
            ->latest('updated_at')->latest('id')->limit(40)->get()
            ->groupBy(fn (Enrollment $e) => $e->student_id . '|' . $e->updated_at->toDateString() . '|' . $e->status)
            ->map(fn ($group) => [
                'student' => $group->first()->student,
                'status' => $group->first()->status,
                'date' => $group->first()->updated_at,
                'subjects' => $group->map(fn (Enrollment $e) => $e->classOffering->subject->code)->unique()->sort()->values(),
            ])
            ->take(5)->values();

        return view('dashboard', $data);
    }
}
