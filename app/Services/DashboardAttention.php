<?php

namespace App\Services;

use App\Models\AbsenceExcuse;
use App\Models\Term;
use App\Models\User;

/** The "Needs attention" list on the dashboards: only things that actually need someone to act. */
class DashboardAttention
{
    /** Excuse letters named one by one in a teacher's notifications; any more are summed up in one line. */
    public const LETTERS_LISTED = 5;

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

        // Past meetings (last two weeks) that never got a sheet: without this a skipped week goes unnoticed
        // and the attendance percentages look better than they are.
        if ($term) {
            $past = MissingSheets::recentCount($term->id, $isTeacher ? $user->id : null);
            if ($past > 0) {
                $items[] = [
                    'count' => $past,
                    'label' => trans_choice('{1} past class meeting has no attendance sheet (last 2 weeks)|[2,*] past class meetings have no attendance sheet (last 2 weeks)', $past),
                    'url' => route('reports.show', ['missing-sheets', 'from' => today()->subDays(14)->toDateString()]),
                    'tone' => 'danger',
                ];
            }
        }

        // Excuse letters waiting for the teacher's decision (made on that day's sheet): one line per student and
        // class, newest first, naming who sent it, so the teacher sees at once that a student sent a letter.
        // A letter covering several days of the same class is one line. Beyond a few, the rest are summed up.
        if ($term && $isTeacher) {
            $letters = AbsenceExcuse::with(['student:id,first_name,last_name', 'classOffering.subject:id,code'])
                ->where('status', AbsenceExcuse::STATUS_PENDING)
                ->whereHas('classOffering', fn ($q) => $q->where('term_id', $term->id)->where('teacher_id', $user->id))
                ->orderBy('date')->get()
                ->groupBy(fn ($letter) => $letter->student_id . '|' . $letter->class_offering_id)
                ->sortByDesc(fn ($group) => $group->max('created_at'))
                ->values();

            foreach ($letters->take(self::LETTERS_LISTED) as $group) {
                $first = $group->first();
                $meetings = $group->count();
                $when = $meetings === 1 ? $first->date->format('M j') : "{$meetings} meetings from " . $first->date->format('M j');
                $items[] = [
                    'count' => $meetings,
                    'label' => "{$first->student->first_name} {$first->student->last_name} sent an excuse letter for {$first->classOffering->subject->code} ({$when})",
                    'url' => route('attendance.sheet', ['class_offering' => $first->class_offering_id, 'date' => $first->date->toDateString()]),
                    'tone' => 'info',
                ];
            }

            $rest = $letters->slice(self::LETTERS_LISTED);
            if ($rest->isNotEmpty()) {
                $count = $rest->sum(fn ($group) => $group->count());
                $oldest = $rest->flatten()->sortBy('date')->first();
                $items[] = [
                    'count' => $count,
                    'label' => trans_choice('{1} more excuse letter is waiting for your decision|[2,*] more excuse letters are waiting for your decision', $count),
                    'url' => route('attendance.sheet', ['class_offering' => $oldest->class_offering_id, 'date' => $oldest->date->toDateString()]),
                    'tone' => 'info',
                ];
            }
        }

        // Photos students submitted, waiting for the registrar (or an administrator) to approve them.
        if (in_array($user->role, ['admin', 'registrar'], true)) {
            $photos = \App\Models\Student::whereNotNull('pending_photo_path')->orderBy('pending_photo_at')->get(['id']);
            if ($photos->isNotEmpty()) {
                $items[] = [
                    'count' => $photos->count(),
                    'label' => trans_choice('{1} student photo is waiting for approval|[2,*] student photos are waiting for approval', $photos->count()),
                    'url' => route('students.show', $photos->first()->id),
                    'tone' => 'info',
                ];
            }
        }

        // Documents students uploaded, waiting to be checked and verified.
        if (in_array($user->role, ['admin', 'registrar'], true)) {
            $uploads = \App\Models\StudentRequirement::where('status', 'submitted')->whereNotNull('file_path')->orderBy('submitted_at')->get(['student_id']);
            if ($uploads->isNotEmpty()) {
                $items[] = [
                    'count' => $uploads->count(),
                    'label' => trans_choice('{1} uploaded document is waiting to be verified|[2,*] uploaded documents are waiting to be verified', $uploads->count()),
                    'url' => route('students.show', $uploads->first()->student_id) . '#pane-requirements',
                    'tone' => 'info',
                ];
            }
        }

        // The staff data privacy policy is still a draft: nobody has been asked to agree yet.
        if ($user->role === 'admin' && ! \App\Support\PrivacyPolicy::published()) {
            $items[] = [
                'count' => 1,
                'label' => 'The data privacy policy is not published yet. Review it and publish it so staff agree to it.',
                'url' => route('privacy-policy.edit'),
                'tone' => 'info',
            ];
        }

        // The administrator sees only work waiting on them (setup, requests, missing sheets, temporary passwords);
        // absence alerts stay on the registrar's and teachers' dashboards and in the Absentees report.
        if ($term && $user->role !== 'admin') {
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

        // Only the administrator can follow this up, and no other page shows it.
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
