<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubjectController extends Controller
{
    public function index(Request $request)
    {
        $subjects = Subject::withCount('classOfferings')
            ->when($request->filled('search'), function ($query) use ($request) {
                $like = '%' . trim((string) $request->input('search')) . '%';
                $query->where(fn ($w) => $w->where('code', 'like', $like)->orWhere('title', 'like', $like));
            })
            ->orderBy('code')
            ->paginate(15)
            ->withQueryString();

        return view('subjects.index', compact('subjects'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $subject = Subject::create($data);

        AuditLogger::log('subject.created', $subject, "Created subject {$subject->code}", null, $data);

        return redirect()->route('subjects.index')->with('success', 'Subject added.');
    }

    public function update(Request $request, Subject $subject)
    {
        $data = $this->validated($request, $subject);
        $old = $subject->only(['code', 'title', 'units', 'description', 'is_active']);
        $subject->update($data);

        AuditLogger::log('subject.updated', $subject, "Updated subject {$subject->code}", $old, $data);

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
        ], ['code.regex' => 'Use letters, numbers and dashes only.']);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
