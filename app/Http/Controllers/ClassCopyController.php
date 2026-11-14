<?php

namespace App\Http\Controllers;

use App\Models\ClassOffering;
use App\Models\Term;
use App\Services\ScheduleChecker;
use App\Support\AuditLogger;
use App\Support\ScheduleCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Start a new term from an earlier one: copy its classes (subject, block, teacher, capacity and weekly schedule)
 * into the new term. Each copy gets a new schedule code and opens with no students. A class is skipped when the
 * block already has it, the subject or teacher is no longer active, or its time clashes in the new term.
 */
class ClassCopyController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'from_term' => ['required', 'integer', 'exists:terms,id'],
            'to_term' => ['required', 'integer', 'exists:terms,id', 'different:from_term'],
        ], [
            'to_term.different' => 'Choose a different term to copy from.',
        ]);

        $to = Term::with('academicYear')->findOrFail($data['to_term']);
        $from = Term::with('academicYear')->findOrFail($data['from_term']);
        if ($to->isClosed()) {
            return back()->with('error', "{$to->name} · {$to->academicYear->name} is closed; classes cannot be added to it.");
        }

        $sources = ClassOffering::with(['subject', 'teacher', 'meetings'])
            ->where('term_id', $from->id)->where('status', '!=', ClassOffering::STATUS_CANCELLED)
            ->orderBy('class_code')->get();
        if ($sources->isEmpty()) {
            return back()->with('error', "{$from->name} · {$from->academicYear->name} has no classes to copy.");
        }

        $created = [];
        $skipped = [];

        DB::transaction(function () use ($sources, $to, &$created, &$skipped) {
            Term::whereKey($to->id)->lockForUpdate()->first();   // schedule codes stay unique

            foreach ($sources as $source) {
                $label = "{$source->subject->code} {$source->block_label}";

                $exists = ClassOffering::where('term_id', $to->id)->where('subject_id', $source->subject_id)->where('section', $source->section)
                    ->where(fn ($q) => $source->program_id ? $q->where('program_id', $source->program_id) : $q->whereNull('program_id'))
                    ->where(fn ($q) => $source->year_level ? $q->where('year_level', $source->year_level) : $q->whereNull('year_level'))
                    ->exists();
                if ($exists) {
                    $skipped[] = "{$label}: already in this term";
                    continue;
                }
                if (! $source->subject->is_active) {
                    $skipped[] = "{$label}: the subject is inactive";
                    continue;
                }
                if (! $source->teacher?->is_active || $source->teacher->role !== 'teacher') {
                    $skipped[] = "{$label}: the teacher is no longer active";
                    continue;
                }

                $meetings = $source->meetings->map(fn ($m) => [
                    'day' => $m->day_of_week, 'start' => substr($m->start_time, 0, 5), 'end' => substr($m->end_time, 0, 5), 'room' => $m->room,
                ])->all();
                $conflicts = ScheduleChecker::classConflicts($to->id, (int) $source->teacher_id, $meetings);
                if ($conflicts !== []) {
                    $skipped[] = "{$label}: " . rtrim($conflicts[0], '.');
                    continue;
                }

                $copy = ClassOffering::create([
                    'term_id' => $to->id,
                    'subject_id' => $source->subject_id,
                    'program_id' => $source->program_id,
                    'year_level' => $source->year_level,
                    'section' => $source->section,
                    'class_code' => ScheduleCode::next($to),
                    'teacher_id' => $source->teacher_id,
                    'capacity' => $source->capacity,
                    'status' => ClassOffering::STATUS_OPEN,
                ]);
                $copy->meetings()->createMany($source->meetings->map(fn ($m) => [
                    'day_of_week' => $m->day_of_week, 'start_time' => $m->start_time, 'end_time' => $m->end_time, 'room' => $m->room,
                ])->all());
                $created[] = $copy->class_code;
            }
        });

        AuditLogger::log('class.copied', null,
            "Copied " . count($created) . " class(es) from {$from->name} · {$from->academicYear->name} to {$to->name} · {$to->academicYear->name}"
            . ($skipped ? ' (' . count($skipped) . ' skipped)' : ''), null, ['created' => $created, 'skipped' => $skipped]);

        $redirect = redirect()->route('classes.index', ['term' => $to->id]);
        $message = count($created) . ' ' . (count($created) === 1 ? 'class' : 'classes') . " copied from {$from->name} · {$from->academicYear->name}."
            . (count($created) ? ' Check the teachers and rooms, then open enrollment.' : '');

        return $skipped === []
            ? $redirect->with('success', $message)
            : $redirect->with(count($created) ? 'success' : 'info', $message)->with('copy_skipped', $skipped);
    }
}
