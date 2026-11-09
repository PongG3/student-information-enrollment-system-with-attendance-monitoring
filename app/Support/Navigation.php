<?php

namespace App\Support;

use App\Models\Term;
use App\Models\User;
use App\Services\DashboardAttention;
use App\Services\DashboardStats;
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
        if (! in_array($user->role, ['admin', 'registrar', 'teacher'], true)) {
            return [];
        }

        $version = (int) Cache::get('notifications.version', 0);

        return Cache::remember("notifications.{$version}.{$user->id}", 60, function () use ($user) {
            $term = Term::where('is_current', true)->first();
            $isTeacher = $user->role === 'teacher';
            $today = DashboardStats::today($term, $isTeacher ? $user->id : null);
            $overview = $isTeacher ? null : DashboardStats::overview($term);

            return DashboardAttention::items($user, $term, $today, $overview);
        });
    }

    /** Something the notifications are built from changed: everyone's list is rebuilt on their next page. */
    public static function refreshNotifications(): void
    {
        Cache::forever('notifications.version', (int) Cache::get('notifications.version', 0) + 1);
    }
}
