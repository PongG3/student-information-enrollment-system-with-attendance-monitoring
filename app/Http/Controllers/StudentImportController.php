<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\Requirement;
use App\Models\Student;
use App\Models\StudentRequirement;
use App\Services\StudentNumberGenerator;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Register many students from a CSV file: upload, check every row (preview), then save the good rows.
 * Nothing is saved until the registrar confirms the preview.
 */
class StudentImportController extends Controller
{
    /** Columns of the template, in order. Starred ones are required. */
    public const COLUMNS = [
        'last_name' => true, 'first_name' => true, 'middle_name' => false, 'suffix' => false,
        'gender' => true, 'birth_date' => true, 'program' => true, 'year_level' => true, 'section' => false,
        'student_type' => true, 'email' => false, 'contact_number' => false, 'address' => false, 'previous_school' => false,
        'guardian_name' => true, 'guardian_relationship' => true, 'guardian_contact' => true,
    ];

    private const MAX_ROWS = 500;
    private const SESSION_KEY = 'student_import';
    private const NAME_RULE = "regex:/^[\\pL\\s\\-\\.'’]+$/u";

    public function create(Request $request)
    {
        $preview = $request->session()->get(self::SESSION_KEY);

        return view('students.import', [
            'columns' => self::COLUMNS,
            'preview' => $request->boolean('preview') ? $preview : null,
            'programs' => Program::where('is_active', true)->orderBy('code')->pluck('code'),
        ]);
    }

    /** A blank CSV with the headings and one example row. */
    public function template()
    {
        $example = ['Dela Cruz', 'Juan', 'Santos', '', 'Male', '2008-05-14', Program::where('is_active', true)->value('code') ?? 'BSCS',
            '1', 'A', 'new', 'juan@example.com', '0917-000-0000', 'Imus, Cavite', 'Sample High School', 'Maria Dela Cruz', 'Mother', '0917-111-1111'];

        return response()->streamDownload(function () use ($example) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_keys(self::COLUMNS));
            fputcsv($out, $example);
            fclose($out);
        }, 'student-import-template.csv', ['Content-Type' => 'text/csv']);
    }

    /** Read the file and check every row; keep the result for the confirmation step. */
    public function preview(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:1024'],
        ], ['file.mimes' => 'Upload a .csv file (save the spreadsheet as CSV first).']);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = fgetcsv($handle) ?: [];
        // Spreadsheet programs may add a byte-order mark and change the case of headings.
        $header = array_map(fn ($h) => strtolower(trim(str_replace(["\u{FEFF}", ' '], ['', '_'], (string) $h))), $header);

        $missing = array_keys(array_filter(self::COLUMNS, fn ($required, $column) => $required && ! in_array($column, $header, true), ARRAY_FILTER_USE_BOTH));
        if ($missing !== []) {
            fclose($handle);

            return back()->withErrors(['file' => 'These required columns are missing: ' . implode(', ', $missing) . '. Use the template.']);
        }

        $programs = Program::where('is_active', true)->pluck('id', 'code')->mapWithKeys(fn ($id, $code) => [strtoupper($code) => $id]);
        $rows = [];
        $seen = [];
        $line = 1;

        while (($cells = fgetcsv($handle)) !== false) {
            $line++;
            if (count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;   // blank line
            }
            if (count($rows) >= self::MAX_ROWS) {
                fclose($handle);

                return back()->withErrors(['file' => 'A file can have at most ' . self::MAX_ROWS . ' students. Split it into smaller files.']);
            }

            $raw = [];
            foreach ($header as $i => $column) {
                $raw[$column] = trim((string) ($cells[$i] ?? ''));
            }

            [$data, $errors] = $this->checkRow($raw, $programs->all());

            // The same person twice (in the file or already registered).
            if ($errors === []) {
                $key = mb_strtolower($data['last_name'] . '|' . $data['first_name'] . '|' . $data['birth_date']);
                if (isset($seen[$key])) {
                    $errors[] = "Same student as line {$seen[$key]}.";
                } elseif (Student::withTrashed()->whereRaw('lower(last_name) = ?', [mb_strtolower($data['last_name'])])
                    ->whereRaw('lower(first_name) = ?', [mb_strtolower($data['first_name'])])
                    ->whereDate('birth_date', $data['birth_date'])->exists()) {
                    $errors[] = 'Already registered (same name and birth date).';
                }
                $seen[$key] = $line;
            }

            $rows[] = ['line' => $line, 'name' => trim(($raw['last_name'] ?? '') . ', ' . ($raw['first_name'] ?? ''), ', '), 'program' => $raw['program'] ?? '', 'data' => $data, 'errors' => $errors];
        }
        fclose($handle);

        if ($rows === []) {
            return back()->withErrors(['file' => 'The file has headings but no students.']);
        }

        $request->session()->put(self::SESSION_KEY, [
            'file' => $request->file('file')->getClientOriginalName(),
            'rows' => $rows,
        ]);

        return redirect()->route('students.import', ['preview' => 1]);
    }

    /** Save the rows that passed. */
    public function store(Request $request)
    {
        $request->validate(['consent' => ['accepted']], ['consent.accepted' => 'Confirm that these students agreed to the privacy notice.']);

        $preview = $request->session()->get(self::SESSION_KEY);
        if (! $preview) {
            return redirect()->route('students.import')->withErrors(['file' => 'Upload the file again; the preview has expired.']);
        }

        $good = array_values(array_filter($preview['rows'], fn ($row) => $row['errors'] === []));
        if ($good === []) {
            return redirect()->route('students.import', ['preview' => 1])->withErrors(['file' => 'No row can be imported. Fix the file and upload it again.']);
        }

        $requirements = Requirement::where('is_active', true)->pluck('id');

        $created = DB::transaction(function () use ($good, $requirements) {
            $count = 0;
            foreach ($good as $row) {
                $data = $row['data'];
                $guardian = $data['guardian'];
                unset($data['guardian']);

                $student = Student::create($data + [
                    'student_number' => StudentNumberGenerator::next(),
                    'status' => Student::STATUS_ACTIVE,
                    'privacy_consent_at' => now(),
                ]);
                $student->guardians()->create($guardian + ['is_emergency_contact' => true]);
                foreach ($requirements as $requirementId) {
                    StudentRequirement::firstOrCreate(['student_id' => $student->id, 'requirement_id' => $requirementId]);
                }
                $count++;
            }

            return $count;
        });

        $skipped = count($preview['rows']) - $created;
        $request->session()->forget(self::SESSION_KEY);
        AuditLogger::log('student.imported', null, "Imported {$created} students from {$preview['file']}");

        return redirect()->route('students.index')->with('success', "{$created} " . ($created === 1 ? 'student' : 'students') . ' registered.'
            . ($skipped > 0 ? " {$skipped} " . ($skipped === 1 ? 'row was' : 'rows were') . ' skipped because of errors.' : ''));
    }

    public function cancel(Request $request)
    {
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('students.import');
    }

    /**
     * Check one row with the same rules as the Add Student form.
     *
     * @return array{0: array, 1: string[]}
     */
    private function checkRow(array $raw, array $programs): array
    {
        $values = [
            'last_name' => $raw['last_name'] ?? '',
            'first_name' => $raw['first_name'] ?? '',
            'middle_name' => ($raw['middle_name'] ?? '') ?: null,
            'suffix' => ($raw['suffix'] ?? '') ?: null,
            'gender' => ucfirst(strtolower($raw['gender'] ?? '')),
            'birth_date' => $raw['birth_date'] ?? '',
            'program' => strtoupper($raw['program'] ?? ''),
            'year_level' => $raw['year_level'] ?? '',
            'section' => ($raw['section'] ?? '') !== '' ? mb_strtoupper($raw['section']) : null,
            'student_type' => strtolower($raw['student_type'] ?? ''),
            'email' => ($raw['email'] ?? '') ?: null,
            'contact_number' => ($raw['contact_number'] ?? '') ?: null,
            'address' => ($raw['address'] ?? '') ?: null,
            'previous_school' => ($raw['previous_school'] ?? '') ?: null,
            'guardian_name' => $raw['guardian_name'] ?? '',
            'guardian_relationship' => $raw['guardian_relationship'] ?? '',
            'guardian_contact' => $raw['guardian_contact'] ?? '',
        ];

        $name = ['string', 'max:100', self::NAME_RULE];
        $phone = 'regex:/^[0-9+\-\s()]{7,20}$/';
        $validator = Validator::make($values, [
            'last_name' => array_merge(['required'], $name),
            'first_name' => array_merge(['required'], $name),
            'middle_name' => array_merge(['nullable'], $name),
            'suffix' => ['nullable', 'string', 'max:10'],
            'gender' => ['required', Rule::in(['Male', 'Female', 'Other'])],
            'birth_date' => ['required', 'date_format:Y-m-d', 'before:today', 'after:1900-01-01'],
            'program' => ['required', Rule::in(array_keys($programs))],
            'year_level' => ['required', 'integer', 'between:1,6'],
            'section' => ['nullable', 'string', 'max:20', 'regex:/^[\pL0-9]+([ \-][\pL0-9]+)*$/u'],
            'student_type' => ['required', Rule::in([Student::TYPE_NEW, Student::TYPE_RETURNING, Student::TYPE_TRANSFEREE])],
            'email' => ['nullable', 'email', 'max:255'],
            'contact_number' => ['nullable', $phone],
            'address' => ['nullable', 'string', 'max:500'],
            'previous_school' => ['nullable', 'string', 'max:255'],
            'guardian_name' => ['required', 'string', 'max:150'],
            'guardian_relationship' => ['required', 'string', 'max:50'],
            'guardian_contact' => ['required', $phone],
        ], [
            'birth_date.date_format' => 'Birth date must look like 2008-05-14.',
            'program.in' => 'Unknown program code. Use one of the active programs.',
            'gender.in' => 'Gender must be Male, Female or Other.',
            'student_type.in' => 'Student type must be new, returning or transferee.',
            'contact_number.regex' => 'Enter a valid phone number.',
            'guardian_contact.regex' => 'Enter a valid guardian phone number.',
        ]);

        if ($validator->fails()) {
            return [[], $validator->errors()->all()];
        }

        $v = $validator->validated();

        return [[
            'last_name' => $v['last_name'], 'first_name' => $v['first_name'], 'middle_name' => $v['middle_name'] ?? null,
            'suffix' => $v['suffix'] ?? null, 'gender' => $v['gender'], 'birth_date' => $v['birth_date'],
            'program_id' => $programs[$v['program']], 'year_level' => (int) $v['year_level'], 'section' => $v['section'] ?? null,
            'student_type' => $v['student_type'], 'email' => $v['email'] ?? null, 'contact_number' => $v['contact_number'] ?? null,
            'address' => $v['address'] ?? null, 'previous_school' => $v['previous_school'] ?? null,
            'guardian' => ['full_name' => $v['guardian_name'], 'relationship' => $v['guardian_relationship'], 'contact_number' => $v['guardian_contact']],
        ], []];
    }
}
