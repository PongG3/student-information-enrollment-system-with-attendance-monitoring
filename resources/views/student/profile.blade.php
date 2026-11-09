<x-app-layout title="My Profile" subtitle="Your record as the registrar holds it. To correct anything, please ask the registrar's office.">
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card-sies p-4">
                <div class="text-center mb-3">
                    <div class="rounded-circle bg-light text-secondary fw-bold" style="width: 96px; height: 96px; display: inline-grid; place-items: center; font-size: 1.8rem">
                        {{ strtoupper(substr($student->first_name, 0, 1) . substr($student->last_name, 0, 1)) }}
                    </div>
                    <h2 class="h5 fw-bold mt-3 mb-1">{{ $student->full_name }}</h2>
                    <div class="text-secondary small mb-2">{{ $student->student_number }}</div>
                    <x-status-badge :value="$student->status" />
                </div>
                <dl class="small mb-0">
                    <dt class="text-secondary">Program</dt><dd>{{ $student->program->code }} · {{ $student->program->name }}</dd>
                    <dt class="text-secondary">Year level / type</dt><dd>Year {{ $student->year_level }} · {{ ucfirst($student->student_type) }}</dd>
                    <dt class="text-secondary">Gender</dt><dd>{{ $student->gender }}</dd>
                    <dt class="text-secondary">Date of birth</dt><dd>{{ $student->birth_date->format('M j, Y') }}</dd>
                    <dt class="text-secondary">Email</dt><dd>{{ $student->email ?: '—' }}</dd>
                    <dt class="text-secondary">Contact number</dt><dd>{{ $student->contact_number ?: '—' }}</dd>
                    <dt class="text-secondary">Address</dt><dd>{{ $student->address ?: '—' }}</dd>
                    <dt class="text-secondary">Previous school</dt><dd class="mb-0">{{ $student->previous_school ?: '—' }}</dd>
                </dl>
            </div>
        </div>

        <div class="col-lg-8">
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
                <div class="card-header-sies"><h2 class="h6 fw-bold mb-0">Admission requirements</h2></div>
                @if ($requirements->isEmpty())
                    <x-empty-state message="No requirements are set up." />
                @else
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
