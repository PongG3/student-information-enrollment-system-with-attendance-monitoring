<?php

namespace App\Http\Controllers;

use App\Models\AbsenceExcuse;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\StudentRequirement;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Like;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * The Archive: records taken out of the active lists. Staff see what they archived themselves; administrators see
 * everything, with who archived it. Anything can be restored by whoever may see it; only administrators may delete
 * permanently. Students are the archivable record today; a new type is one more entry in TYPES and its queries.
 */
class ArchiveController extends Controller
{
    public const TYPES = ['student' => 'Student'];

    public function index(Request $request)
    {
        $user = $request->user();
        $isAdmin = $user->role === 'admin';

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(array_keys(self::TYPES))],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'by' => ['nullable', 'integer'],
        ]);

        $items = $this->students($filters, $user, $isAdmin);

        return view('archive.index', [
            'items' => $items,
            'isAdmin' => $isAdmin,
            'types' => self::TYPES,
            // Admins can filter by who archived; only people who archived something are listed.
            'archivers' => $isAdmin
                ? User::whereIn('id', Student::onlyTrashed()->whereNotNull('archived_by')->distinct()->pluck('archived_by'))->orderBy('name')->get(['id', 'name', 'role'])
                : collect(),
            'filtered' => collect($filters)->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty(),
        ]);
    }

    /** Archived students as archive rows (newest first). */
    private function students(array $f, User $user, bool $isAdmin): LengthAwarePaginator
    {
        $query = Student::onlyTrashed()->with(['program', 'archiver'])
            ->when(! $isAdmin, fn ($q) => $q->where('archived_by', $user->id))
            ->when($isAdmin && ! empty($f['by']), fn ($q) => $q->where('archived_by', (int) $f['by']))
            ->when(($f['type'] ?? null) && $f['type'] !== 'student', fn ($q) => $q->whereRaw('1 = 0'))
            ->when(! empty($f['from']), fn ($q) => $q->whereDate('deleted_at', '>=', $f['from']))
            ->when(! empty($f['to']), fn ($q) => $q->whereDate('deleted_at', '<=', $f['to']))
            ->when(! empty($f['search']), function ($q) use ($f) {
                foreach (preg_split('/\s+/', trim($f['search'])) as $word) {
                    $like = Like::contains($word);
                    $q->where(fn ($w) => $w->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)
                        ->orWhere('student_number', 'like', $like)->orWhere('email', 'like', $like));
                }
            })
            ->orderByDesc('deleted_at')->orderByDesc('id');

        return $query->paginate(15)->withQueryString()->through(fn (Student $s) => [
            'type' => 'student',
            'id' => $s->id,
            'name' => $s->full_name,
            'detail' => $s->student_number . ($s->program ? ' · ' . $s->program->code . ' ' . $s->year_level : ''),
            'url' => route('students.show', $s),
            'archived_at' => $s->deleted_at,
            'archived_by' => $s->archiver?->name,
        ]);
    }

    /** Put an archived record back in its active list. */
    public function restore(Request $request, string $type, int $id)
    {
        $student = $this->find($request, $type, $id);
        $student->restore();
        $student->forceFill(['archived_by' => null])->save();

        AuditLogger::log('student.restored', $student, "Restored student {$student->student_number} ({$student->full_name}) from the archive");

        return back()->with('success', "{$student->first_name} {$student->last_name} was restored to Student Records.");
    }

    /**
     * Delete for good (administrators only). A student with enrollment or attendance history is never deleted:
     * those are academic records the school must keep, so they stay archived.
     */
    public function destroy(Request $request, string $type, int $id)
    {
        abort_unless($request->user()->role === 'admin', 403);
        $student = $this->find($request, $type, $id);

        $history = Enrollment::where('student_id', $student->id)->exists()
            || AttendanceRecord::where('student_id', $student->id)->exists();
        if ($history) {
            return back()->with('error', "{$student->full_name} has enrollment or attendance history. Academic records are kept, so this student stays archived and cannot be deleted.");
        }

        $name = trim($student->first_name . ' ' . $student->last_name);
        $number = $student->student_number;
        $files = array_filter(array_merge(
            [$student->photo_path, $student->pending_photo_path],
            StudentRequirement::where('student_id', $student->id)->pluck('file_path')->all(),
            AbsenceExcuse::where('student_id', $student->id)->pluck('file_path')->all(),
        ));
        $account = $student->user_id ? User::where('id', $student->user_id)->where('role', 'student')->first() : null;

        DB::transaction(function () use ($student, $account) {
            $student->forceDelete();   // guardians, documents, excuse letters and credits go with it
            $account?->delete();       // their portal account has nothing left to open
        });
        Storage::disk('local')->delete($files);

        AuditLogger::log('student.deleted', null, "Permanently deleted archived student {$number} ({$name})", ['student_number' => $number, 'name' => $name], null);

        return back()->with('success', "{$name} was permanently deleted.");
    }

    /** The archived record, if this user may act on it (their own archive, or any for administrators). */
    private function find(Request $request, string $type, int $id): Student
    {
        abort_unless(isset(self::TYPES[$type]), 404);
        $student = Student::onlyTrashed()->findOrFail($id);
        $user = $request->user();
        abort_unless($user->role === 'admin' || (int) $student->archived_by === (int) $user->id, 403);

        return $student;
    }
}
