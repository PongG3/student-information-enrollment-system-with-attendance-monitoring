<?php

namespace App\Http\Controllers;

use App\Models\ClassOffering;
use App\Models\Student;
use Illuminate\Http\Request;

/** The search box in the top bar: students (staff only) and classes. */
class SearchController extends Controller
{
    private const LIMIT = 10;

    public function __invoke(Request $request)
    {
        $user = $request->user();
        $query = trim(preg_replace('/\s+/', ' ', (string) $request->input('q', '')));
        $query = mb_substr($query, 0, 60);
        $searched = mb_strlen($query) >= 2;

        $students = collect();
        $classes = collect();

        if ($searched) {
            $words = array_filter(explode(' ', $query), fn ($word) => $word !== '');
            $like = fn (string $word) => '%' . addcslashes($word, '%_\\') . '%';

            // Teachers do not browse student records, so only staff with that access see students.
            if (in_array($user->role, ['admin', 'registrar'], true)) {
                $students = Student::query()->with('program')
                    ->where(function ($outer) use ($words, $like) {
                        foreach ($words as $word) {
                            $outer->where(fn ($w) => $w->where('student_number', 'like', $like($word))
                                ->orWhere('first_name', 'like', $like($word))
                                ->orWhere('middle_name', 'like', $like($word))
                                ->orWhere('last_name', 'like', $like($word)));
                        }
                    })
                    ->orderBy('last_name')->orderBy('first_name')
                    ->limit(self::LIMIT)->get();
            }

            $classes = ClassOffering::query()->with(['subject', 'term.academicYear'])
                ->when($user->role === 'teacher', fn ($q) => $q->where('teacher_id', $user->id))
                ->where(function ($outer) use ($words, $like) {
                    foreach ($words as $word) {
                        $outer->where(fn ($w) => $w->where('class_code', 'like', $like($word))
                            ->orWhereHas('subject', fn ($s) => $s->where('code', 'like', $like($word))->orWhere('title', 'like', $like($word))));
                    }
                })
                ->orderByDesc('term_id')->orderBy('class_code')
                ->limit(self::LIMIT)->get();
        }

        return view('search', [
            'query' => $query,
            'searched' => $searched,
            'students' => $students,
            'classes' => $classes,
            'showStudents' => in_array($user->role, ['admin', 'registrar'], true),
        ]);
    }
}
