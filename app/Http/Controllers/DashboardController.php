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

        $termCounts = DashboardStats::termCounts($term, $teacherId);

        $data = [
            'greeting' => $greeting,
            'term' => $term,
            'isTeacher' => $isTeacher,
            'today' => DashboardStats::today($term, $teacherId),
            'attendanceTrend' => DashboardStats::attendanceTrend($term, $teacherId),
            'termCounts' => $termCounts,
            'termPercentage' => AttendanceStats::percentage($termCounts),
        ];

        if ($isTeacher) {
            $classIds = $term
                ? ClassOffering::where('teacher_id', $user->id)->where('term_id', $term->id)->where('status', '!=', ClassOffering::STATUS_CANCELLED)->pluck('id')
                : collect();

            $data['teacherCards'] = [
                'classes' => $classIds->count(),
                'students' => Enrollment::whereIn('class_offering_id', $classIds)->where('status', Enrollment::STATUS_ENROLLED)->distinct()->count('student_id'),
            ];
            $data['flagged'] = $term ? AttendanceStats::flagged($term, $user->id, null, 5) : collect();
            $data['attention'] = DashboardAttention::items($user, $term, $data['today'], null);

            return view('dashboard', $data);
        }

        $data['overview'] = DashboardStats::overview($term);
        $data['week'] = DashboardWeek::changes($term);
        $data['attention'] = DashboardAttention::items($user, $term, $data['today'], $data['overview']);
        $data['enrollmentTrend'] = DashboardStats::enrollmentTrend($term);
        $data['enrollmentStatus'] = DashboardStats::enrollmentStatus($term);
        $data['byProgram'] = DashboardStats::byProgram();

        if ($user->role === 'admin') {
            $data['recent'] = AuditLog::with('user')->latest('created_at')->latest('id')->limit(8)->get();
        } else {
            $data['recentRequests'] = Enrollment::with(['student', 'classOffering.subject'])
                ->latest('updated_at')->latest('id')->limit(8)->get();
        }

        return view('dashboard', $data);
    }
}
