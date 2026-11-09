@php($editing = $student->exists)
<x-app-layout :title="$editing ? 'Edit Student' : 'Register Student'"
    :subtitle="$editing ? $student->student_number . ' · ' . $student->full_name : 'The student ID is created automatically when you save.'">
    <form method="POST" enctype="multipart/form-data"
          action="{{ $editing ? route('students.update', $student) : route('students.store') }}" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="card-sies p-4 mb-4">
            <h2 class="h6 fw-bold mb-3">Personal information</h2>
            <div class="row">
                <div class="col-md-4"><x-field name="first_name" label="First name" :value="$student->first_name" required maxlength="100" /></div>
                <div class="col-md-3"><x-field name="middle_name" label="Middle name" :value="$student->middle_name" maxlength="100" /></div>
                <div class="col-md-3"><x-field name="last_name" label="Last name" :value="$student->last_name" required maxlength="100" /></div>
                <div class="col-md-2"><x-field name="suffix" label="Suffix" :value="$student->suffix" maxlength="10" placeholder="Jr., III" /></div>
                <div class="col-md-4">
                    <x-select name="gender" label="Gender" :options="['Male' => 'Male', 'Female' => 'Female', 'Other' => 'Other']"
                        :value="$student->gender" placeholder="Choose" required />
                </div>
                <div class="col-md-4"><x-field name="birth_date" type="date" label="Date of birth" :value="$student->birth_date?->toDateString()" required /></div>
                <div class="col-md-4"><x-field name="previous_school" label="Previous school" :value="$student->previous_school" maxlength="255" /></div>
            </div>
        </div>

        <div class="card-sies p-4 mb-4">
            <h2 class="h6 fw-bold mb-3">Contact</h2>
            <div class="row">
                <div class="col-md-6"><x-field name="address" type="textarea" label="Address" :value="$student->address" maxlength="500" /></div>
                <div class="col-md-3"><x-field name="contact_number" label="Contact number" :value="$student->contact_number" maxlength="20" /></div>
                <div class="col-md-3"><x-field name="email" type="email" label="Email address" :value="$student->email" maxlength="255" /></div>
            </div>
        </div>

        <div class="card-sies p-4 mb-4">
            <h2 class="h6 fw-bold mb-3">Academic information</h2>
            <div class="row">
                <div class="col-md-4">
                    <x-select name="program_id" label="Program" :options="$programs->mapWithKeys(fn ($p) => [$p->id => $p->code . ' · ' . $p->name])->all()"
                        :value="$student->program_id" placeholder="Choose a program" required />
                </div>
                <div class="col-md-2">
                    <x-select name="year_level" label="Year level" :options="array_combine(range(1, 6), range(1, 6))" :value="$student->year_level" required />
                </div>
                <div class="col-md-2">
                    <x-field name="section" label="Block section" :value="$student->section" maxlength="20" placeholder="e.g. A"
                        help="A → BSIT 1-A" />
                </div>
                <div class="col-md-2">
                    <x-select name="student_type" label="Student type"
                        :options="['new' => 'New', 'returning' => 'Returning', 'transferee' => 'Transferee']" :value="$student->student_type" required />
                </div>
                @if ($editing)
                    <div class="col-md-2">
                        <x-select name="status" label="Status"
                            :options="['active' => 'Active', 'inactive' => 'Inactive', 'graduated' => 'Graduated', 'withdrawn' => 'Withdrawn']"
                            :value="$student->status" required />
                    </div>
                @endif
            </div>
        </div>

        <div class="card-sies p-4 mb-4">
            <h2 class="h6 fw-bold mb-1">Parents / guardians</h2>
            <p class="text-secondary small">At least one guardian is required, and one must be the emergency contact.</p>

            @foreach (array_merge($errors->get('guardians'), ...array_values($errors->get('guardians.*'))) as $message)
                <div class="alert alert-danger py-2 small">{{ $message }}</div>
            @endforeach

            @foreach ($guardianRows as $i => $row)
                <div class="border rounded-3 p-3 mb-3">
                    <div class="fw-semibold small mb-2">Guardian {{ $i + 1 }}</div>
                    <div class="row">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold" for="g{{ $i }}name">Full name</label>
                            <input id="g{{ $i }}name" type="text" name="guardians[{{ $i }}][full_name]" maxlength="150" class="form-control mb-3"
                                value="{{ old("guardians.$i.full_name", $row['full_name']) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold" for="g{{ $i }}rel">Relationship</label>
                            <input id="g{{ $i }}rel" type="text" name="guardians[{{ $i }}][relationship]" maxlength="50" class="form-control mb-3"
                                placeholder="e.g. Mother" value="{{ old("guardians.$i.relationship", $row['relationship']) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold" for="g{{ $i }}tel">Contact number</label>
                            <input id="g{{ $i }}tel" type="text" name="guardians[{{ $i }}][contact_number]" maxlength="20" class="form-control mb-3"
                                value="{{ old("guardians.$i.contact_number", $row['contact_number']) }}">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="g{{ $i }}em" name="guardians[{{ $i }}][is_emergency_contact]" value="1"
                                    @checked(session()->hasOldInput() ? (bool) old("guardians.$i.is_emergency_contact") : (bool) $row['is_emergency_contact'])>
                                <label class="form-check-label small" for="g{{ $i }}em">Emergency contact</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold" for="g{{ $i }}addr">Address (optional)</label>
                            <input id="g{{ $i }}addr" type="text" name="guardians[{{ $i }}][address]" maxlength="500" class="form-control"
                                value="{{ old("guardians.$i.address", $row['address']) }}">
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card-sies p-4 mb-4">
            <h2 class="h6 fw-bold mb-3">Photograph</h2>
            @if ($editing && $student->photo_path)
                <img src="{{ route('students.photo', $student) }}" alt="Current photo of {{ $student->full_name }}"
                     class="rounded-3 mb-3 d-block" style="width: 110px; height: 110px; object-fit: cover">
            @endif
            <x-field name="photo" type="file" :label="$editing && $student->photo_path ? 'Replace photo' : 'Upload photo'"
                accept="image/png,image/jpeg" help="JPG or PNG, up to 2 MB. Photos are stored privately and shown only to staff." />
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-navy px-4">{{ $editing ? 'Save changes' : 'Register student' }}</button>
            <a href="{{ $editing ? route('students.show', $student) : route('students.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
        </div>
    </form>
</x-app-layout>
