<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\Program;
use App\Models\Requirement;
use App\Models\Student;
use App\Models\StudentRequirement;
use App\Services\StudentNumberGenerator;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StudentController extends Controller
{
    private const NAME_RULE = "regex:/^[\\pL\\s\\-\\.'’]+$/u";
    private const GUARDIAN_ROWS = 2;

    public function index(Request $request)
    {
        $students = $this->filtered($request)->paginate(15)->withQueryString();

        return view('students.index', [
            'students' => $students,
            'programs' => Program::orderBy('code')->get(),
            'sections' => Student::withTrashed()->whereNotNull('section')->distinct()->orderBy('section')->pluck('section'),
            'archived' => $request->boolean('archived'),
        ]);
    }

    public function export(Request $request)
    {
        $query = $this->filtered($request)->with('guardians');
        $total = (clone $query)->count();

        AuditLogger::log('student.exported', null, "Exported the student list ({$total} records)");

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // lets Excel read accents correctly

            fputcsv($out, [
                'Student ID', 'Last name', 'First name', 'Middle name', 'Suffix', 'Gender', 'Birth date',
                'Program', 'Year level', 'Section', 'Type', 'Status', 'Email', 'Contact number', 'Address',
                'Previous school', 'Guardian', 'Guardian relationship', 'Guardian contact', 'Emergency contact',
            ]);

            $query->chunk(200, function ($students) use ($out) {
                foreach ($students as $student) {
                    $guardian = $student->guardians->sortByDesc('is_emergency_contact')->first();

                    fputcsv($out, array_map([$this, 'csvSafe'], [
                        $student->student_number, $student->last_name, $student->first_name, $student->middle_name,
                        $student->suffix, $student->gender, $student->birth_date->toDateString(),
                        $student->program->code, $student->year_level, $student->section, $student->student_type, $student->status,
                        $student->email, $student->contact_number, $student->address, $student->previous_school,
                        $guardian?->full_name, $guardian?->relationship, $guardian?->contact_number,
                        $guardian?->is_emergency_contact ? 'Yes' : 'No',
                    ]));
                }
            });

            fclose($out);
        }, 'students-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function create()
    {
        return view('students.form', $this->formData(new Student(['year_level' => 1, 'student_type' => 'new', 'status' => 'active']), []));
    }

    public function store(Request $request)
    {
        [$data, $guardians] = $this->validated($request);

        $student = DB::transaction(function () use ($request, $data, $guardians) {
            $data['student_number'] = StudentNumberGenerator::next();
            $data['status'] = Student::STATUS_ACTIVE;

            if ($request->hasFile('photo')) {
                $data['photo_path'] = $request->file('photo')->store('student-photos', 'local');
            }

            $student = Student::create($data);
            $student->guardians()->createMany($guardians);

            foreach (Requirement::where('is_active', true)->get() as $requirement) {
                StudentRequirement::firstOrCreate(['student_id' => $student->id, 'requirement_id' => $requirement->id]);
            }

            return $student;
        });

        AuditLogger::log('student.created', $student, "Registered student {$student->student_number} ({$student->full_name})");

        return redirect()->route('students.show', $student)->with('success', "Student registered with ID {$student->student_number}.");
    }

    public function show(Student $student)
    {
        $student->load(['program', 'guardians']);

        if (! $student->trashed()) {
            foreach (Requirement::where('is_active', true)->get() as $requirement) {
                StudentRequirement::firstOrCreate(['student_id' => $student->id, 'requirement_id' => $requirement->id]);
            }
        }

        return view('students.show', [
            'student' => $student,
            'requirements' => StudentRequirement::with(['requirement', 'verifier'])
                ->where('student_id', $student->id)->get()
                ->sortBy(fn ($row) => $row->requirement->name),
            'enrollments' => $student->enrollments()->with(['classOffering.subject', 'classOffering.term.academicYear'])->latest()->get(),
        ]);
    }

    public function edit(Student $student)
    {
        $student->load('guardians');

        $rows = $student->guardians->sortByDesc('is_emergency_contact')->values()->map(fn ($g) => [
            'full_name' => $g->full_name, 'relationship' => $g->relationship, 'contact_number' => $g->contact_number,
            'address' => $g->address, 'is_emergency_contact' => $g->is_emergency_contact,
        ])->all();

        return view('students.form', $this->formData($student, $rows));
    }

    public function update(Request $request, Student $student)
    {
        [$data, $guardians] = $this->validated($request, $student);

        $old = $student->only(['first_name', 'middle_name', 'last_name', 'suffix', 'gender', 'birth_date', 'program_id', 'year_level', 'section', 'student_type', 'status']);
        $oldPhoto = $student->photo_path;

        DB::transaction(function () use ($request, $student, $data, $guardians) {
            if ($request->hasFile('photo')) {
                $data['photo_path'] = $request->file('photo')->store('student-photos', 'local');
            }

            $student->update($data);
            $student->guardians()->delete();
            $student->guardians()->createMany($guardians);
        });

        if ($request->hasFile('photo') && $oldPhoto) {
            Storage::disk('local')->delete($oldPhoto);
        }

        AuditLogger::log('student.updated', $student, "Updated student {$student->student_number}", $old, collect($data)->except('photo_path')->all());

        return redirect()->route('students.show', $student)->with('success', 'Student record updated.');
    }

    public function archive(Student $student)
    {
        $blocking = Enrollment::where('student_id', $student->id)
            ->whereIn('status', [Enrollment::STATUS_PENDING, Enrollment::STATUS_APPROVED, Enrollment::STATUS_ENROLLED])
            ->whereHas('classOffering.term', fn ($q) => $q->whereNull('closed_at'))
            ->exists();

        if ($blocking) {
            return back()->with('error', 'This student still has pending, approved or enrolled classes in an open term. Cancel those first.');
        }

        $student->delete();

        AuditLogger::log('student.archived', $student, "Archived student {$student->student_number} ({$student->full_name})");

        return redirect()->route('students.index')->with('success', 'Student archived. Their history is kept and they can be restored.');
    }

    public function restore(Student $student)
    {
        $student->restore();

        AuditLogger::log('student.restored', $student, "Restored student {$student->student_number} ({$student->full_name})");

        return redirect()->route('students.show', $student)->with('success', 'Student restored.');
    }

    /** Photos live on the private disk and are served only to staff. */
    public function photo(Student $student)
    {
        abort_unless($student->photo_path && Storage::disk('local')->exists($student->photo_path), 404);

        return Storage::disk('local')->response($student->photo_path, null, ['Cache-Control' => 'private, max-age=3600']);
    }

    private function filtered(Request $request): Builder
    {
        return Student::query()
            ->with('program')
            ->when($request->boolean('archived'), fn ($q) => $q->onlyTrashed())
            ->when($request->filled('search'), function ($query) use ($request) {
                // Every word must match the number, a name, or the email.
                foreach (preg_split('/\s+/', trim((string) $request->input('search'))) as $word) {
                    $like = '%' . $word . '%';
                    $query->where(fn ($w) => $w->where('student_number', 'like', $like)
                        ->orWhere('first_name', 'like', $like)
                        ->orWhere('middle_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('email', 'like', $like));
                }
            })
            ->when($request->filled('program'), fn ($q) => $q->where('program_id', (int) $request->input('program')))
            ->when($request->filled('year_level'), fn ($q) => $q->where('year_level', (int) $request->input('year_level')))
            ->when($request->filled('section'), fn ($q) => $q->where('section', mb_strtoupper((string) $request->input('section'))))
            ->when($request->filled('status'), fn ($q) => $q->where('status', (string) $request->input('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('student_type', (string) $request->input('type')))
            ->orderBy('last_name')
            ->orderBy('first_name');
    }

    private function formData(Student $student, array $guardianRows): array
    {
        $rows = [];
        for ($i = 0; $i < self::GUARDIAN_ROWS; $i++) {
            $rows[$i] = $guardianRows[$i] ?? [
                'full_name' => null, 'relationship' => null, 'contact_number' => null, 'address' => null,
                'is_emergency_contact' => $i === 0 && ! $student->exists,
            ];
        }

        return [
            'student' => $student,
            'guardianRows' => $rows,
            'programs' => Program::where(fn ($q) => $q->where('is_active', true)->orWhere('id', $student->program_id))->orderBy('code')->get(),
        ];
    }

    /** @return array{0: array, 1: array} student fields and cleaned guardian rows */
    private function validated(Request $request, ?Student $student = null): array
    {
        $name = ['string', 'max:100', self::NAME_RULE];

        $data = $request->validate([
            'first_name' => array_merge(['required'], $name),
            'middle_name' => array_merge(['nullable'], $name),
            'last_name' => array_merge(['required'], $name),
            'suffix' => ['nullable', 'string', 'max:10'],
            'gender' => ['required', Rule::in(['Male', 'Female', 'Other'])],
            'birth_date' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'address' => ['nullable', 'string', 'max:500'],
            'contact_number' => ['nullable', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'program_id' => ['required', Rule::exists('programs', 'id')->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $student?->program_id))],
            'year_level' => ['required', 'integer', 'between:1,6'],
            'section' => ['nullable', 'string', 'max:20', 'regex:/^[\pL0-9]+([ \-][\pL0-9]+)*$/u'],
            'student_type' => ['required', Rule::in([Student::TYPE_NEW, Student::TYPE_RETURNING, Student::TYPE_TRANSFEREE])],
            'previous_school' => ['nullable', 'string', 'max:255'],
            'status' => [$student ? 'required' : 'nullable', Rule::in([Student::STATUS_ACTIVE, Student::STATUS_INACTIVE, Student::STATUS_GRADUATED, Student::STATUS_WITHDRAWN])],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ], [
            'contact_number.regex' => 'Enter a valid phone number.',
            'section.regex' => 'Use letters and numbers only, e.g. A or B2.',
            'photo.max' => 'The photo may not be larger than 2 MB.',
        ]);

        unset($data['photo']);

        // "a" and "A" are the same section.
        $data['section'] = isset($data['section']) ? mb_strtoupper(trim($data['section'])) : null;

        return [$data, $this->guardians($request)];
    }

    private function guardians(Request $request): array
    {
        $rows = collect($request->input('guardians', []))
            ->map(fn ($row) => [
                'full_name' => trim((string) ($row['full_name'] ?? '')),
                'relationship' => trim((string) ($row['relationship'] ?? '')),
                'contact_number' => trim((string) ($row['contact_number'] ?? '')),
                'address' => trim((string) ($row['address'] ?? '')),
                'is_emergency_contact' => ! empty($row['is_emergency_contact']),
            ])
            ->filter(fn ($row) => $row['full_name'] !== '' || $row['relationship'] !== '' || $row['contact_number'] !== '' || $row['address'] !== '')
            ->values();

        Validator::make(['guardians' => $rows->all()], [
            'guardians' => ['required', 'array', 'min:1'],
            'guardians.*.full_name' => ['required', 'string', 'max:150'],
            'guardians.*.relationship' => ['required', 'string', 'max:50'],
            'guardians.*.contact_number' => ['required', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'guardians.*.address' => ['nullable', 'string', 'max:500'],
        ], [
            'guardians.required' => 'Add at least one parent or guardian.',
            'guardians.min' => 'Add at least one parent or guardian.',
            'guardians.*.full_name.required' => 'Each guardian needs a name.',
            'guardians.*.relationship.required' => 'Each guardian needs a relationship.',
            'guardians.*.contact_number.required' => 'Each guardian needs a contact number.',
            'guardians.*.contact_number.regex' => 'Enter a valid guardian phone number.',
        ])->validate();

        if (! $rows->contains('is_emergency_contact', true)) {
            throw ValidationException::withMessages(['guardians' => 'Mark at least one guardian as the emergency contact.']);
        }

        return $rows->map(fn ($row) => [
            'full_name' => $row['full_name'],
            'relationship' => $row['relationship'],
            'contact_number' => $row['contact_number'],
            'address' => $row['address'] === '' ? null : $row['address'],
            'is_emergency_contact' => $row['is_emergency_contact'],
        ])->all();
    }

    /** Stop spreadsheet programs from running text as a formula. */
    public function csvSafe(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }
}
