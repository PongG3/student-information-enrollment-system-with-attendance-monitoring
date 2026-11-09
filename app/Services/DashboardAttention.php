<?php

namespace App\Services;

use App\Models\Term;
use App\Models\User;

/** The "Needs attention" list on the dashboards: only things that actually need someone to act. */
class DashboardAttention
{
    /**
     * @param  array  $today     DashboardStats::today() result
     * @param  array|null  $overview  DashboardStats::overview() result (admin and registrar only)
     * @return array<int, array{count: int, label: string, url: string, tone: string}>
     */
    public static function items(User $user, ?Term $term, array $today, ?array $overview): array
    {
        $items = [];
        $isTeacher = $user->role === 'teacher';

        if (! $term && $user->role === 'admin') {
            $items[] = ['count' => 1, 'label' => 'No current term is set. Choose one so classes and attendance work.', 'url' => route('academic-years.index'), 'tone' => 'danger'];
        }

        if ($overview && $overview['pending'] > 0) {
            $items[] = [
                'count' => $overview['pending'],
                'label' => trans_choice('{1} enrollment is waiting for review|[2,*] enrollments are waiting for review', $overview['pending']),
                'url' => route('enrollments.index', array_filter(['status' => 'pending', 'term' => $term?->id])),
                'tone' => 'warning',
            ];
        }

        $missing = max(0, $today['expected'] - $today['recorded']);
        if ($missing > 0) {
            $items[] = [
                'count' => $missing,
                'label' => $isTeacher
                    ? trans_choice('{1} class has no attendance sheet today|[2,*] classes have no attendance sheet today', $missing)
                    : trans_choice('{1} class sheet is not recorded yet today|[2,*] class sheets are not recorded yet today', $missing),
                'url' => route('attendance.index'),
                'tone' => 'warning',
            ];
        }

        if ($term) {
            $flagged = AttendanceStats::flagged($term, $isTeacher ? $user->id : null, null, 500)->count();
            if ($flagged > 0) {
                $items[] = [
                    'count' => $flagged,
                    'label' => trans_choice('{1} student-class record is over the absence limit|[2,*] student-class records are over the absence limit', $flagged),
                    'url' => $isTeacher ? route('attendance.index') : route('reports.index'),
                    'tone' => 'danger',
                ];
            }
        }

        if ($user->role === 'admin') {
            $mustChange = User::where('is_active', true)->where('must_change_password', true)->count();
            if ($mustChange > 0) {
                $items[] = [
                    'count' => $mustChange,
                    'label' => trans_choice('{1} account has not changed its temporary password|[2,*] accounts have not changed their temporary password', $mustChange),
                    'url' => route('users.index'),
                    'tone' => 'info',
                ];
            }
        }

        return $items;
    }
}
