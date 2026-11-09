<?php

namespace App\Http\Controllers;

use App\Models\NonClassDay;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HolidayController extends Controller
{
    public function index(Request $request)
    {
        $year = (int) $request->input('year', today()->year);

        $holidays = NonClassDay::whereYear('date', $year)->orderBy('date')->get();

        $years = NonClassDay::pluck('date')
            ->map(fn ($date) => $date->year)
            ->push(today()->year, $year)
            ->unique()
            ->sortDesc()
            ->values();

        return view('holidays.index', compact('holidays', 'year', 'years'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $holiday = NonClassDay::create($data);

        AuditLogger::log('holiday.created', $holiday, "Added non-class day {$data['date']}: {$data['description']}", null, $data);

        return redirect()->route('holidays.index', ['year' => substr($data['date'], 0, 4)])->with('success', 'Non-class day added.');
    }

    public function update(Request $request, NonClassDay $holiday)
    {
        $data = $this->validated($request, $holiday);
        $old = ['date' => $holiday->date->toDateString(), 'description' => $holiday->description];
        $holiday->update($data);

        AuditLogger::log('holiday.updated', $holiday, "Updated non-class day {$data['date']}", $old, $data);

        return redirect()->route('holidays.index', ['year' => substr($data['date'], 0, 4)])->with('success', 'Non-class day updated.');
    }

    public function destroy(NonClassDay $holiday)
    {
        $date = $holiday->date->toDateString();
        $description = $holiday->description;
        $holiday->delete();

        AuditLogger::log('holiday.deleted', null, "Removed non-class day {$date}: {$description}", ['date' => $date, 'description' => $description]);

        return redirect()->route('holidays.index', ['year' => substr($date, 0, 4)])->with('success', 'Non-class day removed.');
    }

    private function validated(Request $request, ?NonClassDay $holiday = null): array
    {
        return $request->validate([
            'date' => ['bail', 'required', 'date', function ($attribute, $value, $fail) use ($holiday) {
                $taken = NonClassDay::whereDate('date', $value)
                    ->when($holiday, fn ($query) => $query->where('id', '!=', $holiday->id))
                    ->exists();

                if ($taken) {
                    $fail('A non-class day is already recorded for this date.');
                }
            }],
            'description' => ['required', 'string', 'max:150'],
        ]);
    }
}
