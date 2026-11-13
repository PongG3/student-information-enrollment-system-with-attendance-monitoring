<?php

namespace App\Http\Controllers;

use App\Models\DataBreach;
use App\Support\AuditLogger;
use Illuminate\Http\Request;

/** The breach register (administrator only). Entries are kept, never deleted. */
class DataBreachController extends Controller
{
    public function index()
    {
        return view('privacy.breaches', [
            'breaches' => DataBreach::with('recorder')->latest('discovered_at')->paginate(10),
        ]);
    }

    public function store(Request $request)
    {
        $breach = DataBreach::create($this->validated($request) + ['recorded_by' => $request->user()->id, 'status' => 'open']);
        AuditLogger::log('privacy.breach_recorded', $breach, 'Recorded a possible data breach: ' . $breach->summary);

        return redirect()->route('breaches.index')->with('success', 'Recorded. Remember the 72-hour deadline to tell the National Privacy Commission if it is serious.');
    }

    public function update(Request $request, DataBreach $breach)
    {
        $data = $this->validated($request) + ['status' => $request->boolean('closed') ? 'closed' : 'open'];
        $old = $breach->only(array_keys($data));
        $breach->update($data);
        AuditLogger::log('privacy.breach_updated', $breach, 'Updated data breach record: ' . $breach->summary, $old, $data);

        return redirect()->route('breaches.index')->with('success', 'Breach record updated.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'discovered_at' => ['required', 'date', 'before_or_equal:now'],
            'summary' => ['required', 'string', 'max:255'],
            'data_affected' => ['nullable', 'string', 'max:2000'],
            'people_affected' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'actions_taken' => ['nullable', 'string', 'max:4000'],
            'npc_notified_on' => ['nullable', 'date'],
            'subjects_notified_on' => ['nullable', 'date'],
        ]);
    }
}
