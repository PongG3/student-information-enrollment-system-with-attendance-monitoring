<?php

namespace App\Support;

use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use App\Services\DashboardAttention;
use App\Services\DashboardStats;
use App\Services\StudentPortalData;
use Illuminate\Support\Facades\Cache;

/**
 * Breadcrumbs and the notification menu of the top bar.
 * Both are built from what already exists: the role's menu in config/sidebar.php
 * and the "Needs attention" checks of the dashboards.
 */
final class Navigation
{
    /**
     * Dashboard / menu section / this page. Empty on the dashboard itself.
     *
     * @return array<int, array{label: string, url: ?string}>  the last item is the current page (url null)
     */
    public static function breadcrumbs(User $user, ?string $title): array
    {
        $items = config('sidebar.' . $user->role . '.items', []);
        if ($items === []) {
            return [];
        }

        $home = $items[0];
        if (request()->routeIs(...(array) $home['active'])) {
            return [];
        }

        $crumbs = [['label' => $home['label'], 'url' => route($home['route'])]];

        $section = collect($items)->slice(1)->first(fn ($item) => request()->routeIs(...(array) $item['active']));
        if ($section) {
            $crumbs[] = ['label' => $section['label'], 'url' => route($section['route'])];
        }

        if ($title !== null && $title !== '' && $title !== ($section['label'] ?? null)) {
            $crumbs[] = ['label' => $title, 'url' => null];
        } else {
            $crumbs[array_key_last($crumbs)]['url'] = null; // the section page itself
        }

        return $crumbs;
    }

    /**
     * Work waiting on this person (staff only), kept for a minute so pages stay fast.
     *
     * @return array<int, array{count: int, label: string, url: string, tone: string}>
     */
    public static function notifications(User $user): array
    {
        if (! in_array($user->role, ['admin', 'registrar', 'teacher', 'student'], true)) {
            return [];
        }

        $version = (int) Cache::get('notifications.version', 0);

        if ($user->role === 'student') {
            return Cache::remember("notifications.{$version}.{$user->id}", 60, fn () => self::studentNotifications($user));
        }

        return Cache::remember("notifications.{$version}.{$user->id}", 60, function () use ($user) {
            $term = Term::where('is_current', true)->first();
            $isTeacher = $user->role === 'teacher';
            $today = DashboardStats::today($term, $isTeacher ? $user->id : null);
            $overview = $isTeacher ? null : DashboardStats::overview($term);

            return DashboardAttention::items($user, $term, $today, $overview);
        });
    }

    /**
     * A student's notifications: what happened to their requests this week, attendance alerts,
     * and an open enrollment they have not used yet.
     *
     * @return array<int, array{count: int, label: string, url: string, tone: string}>
     */
    private static function studentNotifications(User $user): array
    {
        $student = Student::where('user_id', $user->id)->first();
        if (! $student) {
            return [];
        }

        $items = [];

        // Documents the registrar sent back: the student needs to hand them in again.
        $returned = \App\Models\StudentRequirement::where('student_id', $student->id)
            ->where('status', \App\Models\StudentRequirement::STATUS_PENDING)->whereNotNull('returned_at')->count();
        if ($returned > 0) {
            $items[] = ['count' => $returned, 'label' => trans_choice('{1} document was returned by the registrar|[2,*] documents were returned by the registrar', $returned), 'url' => route('student.profile'), 'tone' => 'danger'];
        }

        $term = Term::where('is_current', true)->first();
        if (! $term) {
            return $items;
        }
        $inTerm = fn ($q) => $q->where('term_id', $term->id);
        $recent = Enrollment::where('student_id', $student->id)->whereHas('classOffering', $inTerm)
            ->where('reviewed_at', '>=', now()->subDays(7));

        foreach ([
            [Enrollment::STATUS_ENROLLED, '{1} class request was enrolled this week|[2,*] class requests were enrolled this week', 'info'],
            [Enrollment::STATUS_APPROVED, '{1} class request was approved this week|[2,*] class requests were approved this week', 'info'],
            [Enrollment::STATUS_REJECTED, '{1} class request was rejected this week|[2,*] class requests were rejected this week', 'danger'],
        ] as [$status, $label, $tone]) {
            $count = (clone $recent)->where('status', $status)->count();
            if ($count > 0) {
                $items[] = ['count' => $count, 'label' => trans_choice($label, $count), 'url' => route('student.enrollments'), 'tone' => $tone];
            }
        }

        $classes = StudentPortalData::enrolledClasses($student, $term);
        $flagged = StudentPortalData::attendance($student, $classes)['rows']->where('flagged', true)->count();
        if ($flagged > 0) {
            $items[] = ['count' => $flagged, 'label' => trans_choice('{1} class is over the absence limit|[2,*] classes are over the absence limit', $flagged), 'url' => route('student.attendance'), 'tone' => 'danger'];
        }

        $hasRequests = Enrollment::where('student_id', $student->id)->whereHas('classOffering', $inTerm)->exists();
        if ($term->enrollment_open && ! $hasRequests) {
            $items[] = ['count' => 1, 'label' => "Enrollment is open. See the registrar's office to enroll.", 'url' => route('student.courses'), 'tone' => 'warning'];
        }

        return $items;
    }

    /** Something the notifications are built from changed: everyone's list is rebuilt on their next page. */
    public static function refreshNotifications(): void
    {
        Cache::forever('notifications.version', (int) Cache::get('notifications.version', 0) + 1);
    }
}
