<x-app-layout title="My Profile" subtitle="Your record as the registrar holds it. To correct anything, please ask the registrar's office.">
    @php
        // [label, value, takes the full width]
        $details = [
            ['Program', $student->program->code . ' · ' . $student->program->name, true],
            ['Year level / type', 'Year ' . $student->year_level . ' · ' . ucfirst($student->student_type), false],
            ['Block section', $student->block_section ?? 'Not assigned', false],
            ['Gender', $student->gender, false],
            ['Date of birth', $student->birth_date->format('M j, Y'), false],
            ['Email', $student->email ?: '—', true],
            ['Contact number', $student->contact_number ?: '—', false],
            ['Previous school', $student->previous_school ?: '—', false],
            ['Address', $student->address ?: '—', true],
        ];
    @endphp
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card-sies p-4 h-100">
                <div class="profile-head">
                    <div class="profile-avatar" aria-hidden="true">
                        {{ strtoupper(substr($student->first_name, 0, 1) . substr($student->last_name, 0, 1)) }}
                    </div>
                    <div>
                        <h2 class="h5 fw-bold mb-1">{{ $student->full_name }}</h2>
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-secondary small">{{ $student->student_number }}</span>
                            <x-status-badge :value="$student->status" />
                        </div>
                    </div>
                </div>
                <dl class="profile-details">
                    @foreach ($details as [$label, $value, $wide])
                        <div @class(['is-wide' => $wide])>
                            <dt>{{ $label }}</dt>
                            <dd>{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card-sies mb-4">
                <div class="card-header-sies"><h2 class="h6 fw-bold mb-0">Parents / guardians</h2></div>
                @if ($student->guardians->isEmpty())
                    <x-empty-state message="No guardian recorded." />
                @else
                    <div class="table-responsive">
                        <table class="table table-sies mb-0">
                            <thead><tr><th>Name</th><th>Relationship</th><th>Contact</th><th>Emergency</th></tr></thead>
                            <tbody>
                                @foreach ($student->guardians->sortByDesc('is_emergency_contact') as $guardian)
                                    <tr>
                                        <td class="fw-semibold">{{ $guardian->full_name }}</td>
                                        <td>{{ $guardian->relationship }}</td>
                                        <td>{{ $guardian->contact_number }}</td>
                                        <td>{{ $guardian->is_emergency_contact ? 'Yes' : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="card-sies">
                @php
                    // Handed in = submitted or already verified by the registrar.
                    $handedIn = $requirements->whereIn('status', ['submitted', 'verified'])->count();
                    $verified = $requirements->where('status', 'verified')->count();
                    $total = $requirements->count();
                    $progress = $total > 0 ? (int) round($handedIn / $total * 100) : 0;
                @endphp
                <div class="card-header-sies">
                    <h2 class="h6 fw-bold mb-0">Admission requirements</h2>
                    @if ($total > 0)
                        <span class="small fw-semibold">{{ $handedIn }} of {{ $total }} submitted{{ $verified ? ' · ' . $verified . ' verified' : '' }}</span>
                    @endif
                </div>
                @if ($requirements->isEmpty())
                    <x-empty-state message="No requirements are set up." />
                @else
                    <div class="px-3 pt-3">
                        <div class="progress" role="progressbar" aria-label="Requirements submitted" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100" style="height: 6px">
                            <div class="progress-bar bg-success" style="width: {{ $progress }}%"></div>
                        </div>
                        @if ($handedIn < $total)
                            <p class="small text-secondary mt-2 mb-0">Bring the pending documents to the registrar's office. They will be marked here once received.</p>
                        @else
                            <p class="small text-success mt-2 mb-0">All requirements are in. Thank you!</p>
                        @endif
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sies mb-0">
                            <thead><tr><th>Requirement</th><th>Status</th></tr></thead>
                            <tbody>
                                @foreach ($requirements as $row)
                                    <tr>
                                        <td class="fw-semibold">{{ $row['requirement']->name }}
                                            @unless ($row['requirement']->is_required)<span class="text-secondary small fw-normal">(optional)</span>@endunless
                                        </td>
                                        <td><x-status-badge :value="$row['status']" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
