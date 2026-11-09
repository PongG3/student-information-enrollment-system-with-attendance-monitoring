<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Support\Like;

class ProgramController extends Controller
{
    public function index(Request $request)
    {
        $programs = Program::withCount('students')
            ->when($request->filled('search'), function ($query) use ($request) {
                $like = Like::contains($request->input('search'));
                $query->where(fn ($w) => $w->where('code', 'like', $like)->orWhere('name', 'like', $like));
            })
            ->orderBy('code')
            ->paginate(15)
            ->withQueryString();

        return view('programs.index', compact('programs'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $program = Program::create($data);

        AuditLogger::log('program.created', $program, "Created program {$program->code}", null, $data);

        return redirect()->route('programs.index')->with('success', 'Program added.');
    }

    public function update(Request $request, Program $program)
    {
        $data = $this->validated($request, $program);
        $old = $program->only(['code', 'name', 'is_active']);
        $program->update($data);

        AuditLogger::log('program.updated', $program, "Updated program {$program->code}", $old, $data);

        return redirect()->route('programs.index')->with('success', 'Program updated.');
    }

    private function validated(Request $request, ?Program $program = null): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        $data = $request->validate([
            'code' => ['required', 'max:20', 'regex:/^[A-Z0-9\-]+$/', Rule::unique('programs', 'code')->ignore($program)],
            'name' => ['required', 'string', 'max:150'],
        ], ['code.regex' => 'Use letters, numbers and dashes only.']);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
