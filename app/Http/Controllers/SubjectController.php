<?php

namespace App\Http\Controllers;

use App\Models\ClassOffering;
use App\Models\Program;
use App\Models\Subject;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Support\Like;

class SubjectController extends Controller
{
    public function index(Request $request)
    {
        $subjects = Subject::with('programs')->withCount('classOfferings')
            ->when($request->filled('search'), function ($query) use ($request) {
                $like = Like::contains($request->input('search'));
                $query->where(fn ($w) => $w->where('code', 'like', $like)->orWhere('title', 'like', $like));
            })
            ->when(in_array($request->input('category'), array_keys(Subject::CATEGORIES), true), fn ($q) => $q->where('category', $request->input('category')))
            ->orderBy('code')
            ->paginate(15)
            ->withQueryString();

        return view('subjects.index', [
            'subjects' => $subjects,
            'programs' => Program::orderBy('code')->get(),
        ]);
    }

    public function store(Request $request)
    {
        [$data, $programIds] = $this->validated($request);
        $subject = Subject::create($data);
        $subject->programs()->sync($programIds);

        AuditLogger::log('subject.created', $subject, "Created subject {$subject->code}", null, $data + ['programs' => $subject->load('programs')->programsLabel()]);

        return redirect()->route('subjects.index')->with('success', 'Subject added.');
    }

    public function update(Request $request, Subject $subject)
    {
        [$data, $programIds] = $this->validated($request, $subject);
        $old = $subject->only(['code', 'title', 'units', 'category', 'description', 'is_active']) + ['programs' => $subject->programsLabel()];
        $subject->update($data);
        $subject->programs()->sync($programIds);

        AuditLogger::log('subject.updated', $subject, "Updated subject {$subject->code}", $old, $data + ['programs' => $subject->load('programs')->programsLabel()]);

        return redirect()->route('subjects.index')->with('success', 'Subject updated.');
    }

    private function validated(Request $request, ?Subject $subject = null): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        $data = $request->validate([
            'code' => ['required', 'max:20', 'regex:/^[A-Z0-9\-]+$/', Rule::unique('subjects', 'code')->ignore($subject)],
            'title' => ['required', 'string', 'max:150'],
            'units' => ['required', 'numeric', 'min:0.5', 'max:12'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category' => ['nullable', Rule::in(array_keys(Subject::CATEGORIES))],
            'program_ids' => ['nullable', 'array'],
            'program_ids.*' => ['integer', 'exists:programs,id'],
        ], ['code.regex' => 'Use letters, numbers and dashes only.']);

        $programIds = array_values(array_unique(array_map('intval', $data['program_ids'] ?? [])));
        unset($data['program_ids']);

        // Major = a program's own subject (needs its programs); minor = general education, open to all.
        $data['category'] ??= $programIds === [] ? Subject::MINOR : Subject::MAJOR;
        if ($data['category'] === Subject::MAJOR && $programIds === []) {
            throw ValidationException::withMessages(['program_ids' => 'A major subject needs at least one program. Tick the programs it belongs to.']);
        }
        if ($data['category'] === Subject::MINOR) {
            $programIds = [];
        }
        $data['is_active'] = $request->boolean('is_active');

        // A program cannot be taken off while this subject still has classes for that program's blocks.
        if ($subject && $programIds !== []) {
            $stranded = ClassOffering::with('program')->where('subject_id', $subject->id)
                ->whereNotNull('program_id')->whereNotIn('program_id', $programIds)->get();
            if ($stranded->isNotEmpty()) {
                throw ValidationException::withMessages(['program_ids' => sprintf(
                    '%s still has classes for %s (%s). Change those classes first, or keep the program.',
                    $subject->code, $stranded->pluck('program.code')->unique()->implode(', '), $stranded->pluck('class_code')->implode(', '),
                )]);
            }
        }

        return [$data, $programIds];
    }
}
