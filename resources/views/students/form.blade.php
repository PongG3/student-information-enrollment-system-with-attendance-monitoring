@php
    $editing = $student->exists;

    // The form is split into tabs so each part fits one screen. Which fields live on which tab
    // decides where the red "has errors" dot goes and which tab opens after a failed save.
    $tabs = [
        'personal' => ['Personal & contact', 'user', ['first_name', 'middle_name', 'last_name', 'suffix', 'gender', 'birth_date', 'previous_school', 'address', 'contact_number', 'email'], true],
        'academic' => ['Academic', 'classes', ['program_id', 'year_level', 'section', 'student_type', 'status'], true],
        'guardians' => ['Guardians', 'students', ['guardians'], true],
        'photo' => ['Photo', 'file', ['photo'], false],
    ];
    $tabHasErrors = fn (array $fields) => collect($fields)->contains(fn ($f) => $errors->has($f) || $errors->has($f . '.*'));
    $openTab = collect($tabs)->search(fn ($tab) => $tabHasErrors($tab[2])) ?: 'personal';
    $keys = array_keys($tabs);
@endphp
<x-app-layout :title="$editing ? 'Edit Student' : 'Register Student'"
    :subtitle="$editing ? $student->student_number . ' · ' . $student->full_name : 'The student ID is created automatically when you save.'"
    :back="$editing ? route('students.show', $student) : route('students.index')" :back-label="$editing ? 'Back to student' : 'Back to student records'">
    <form method="POST" enctype="multipart/form-data"
          action="{{ $editing ? route('students.update', $student) : route('students.store') }}" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif

        @if ($errors->any())
            <div class="alert alert-danger d-flex gap-2 align-items-start" role="alert">
                <x-icon name="alert" :size="18" />
                <div>Please check the fields marked in red. Tabs with a red dot have something to fix. Nothing was saved yet.</div>
            </div>
        @endif

        <ul class="form-tabs" role="tablist" aria-label="Student record parts">
            @foreach ($tabs as $key => [$label, $icon, $fields, $required])
                <li role="presentation">
                    <button type="button" role="tab" id="tab-{{ $key }}" data-bs-toggle="tab" data-bs-target="#pane-{{ $key }}"
                        aria-controls="pane-{{ $key }}" aria-selected="{{ $openTab === $key ? 'true' : 'false' }}"
                        @class(['form-tab', 'active' => $openTab === $key])>
                        <x-icon :name="$icon" :size="16" />
                        <span>{{ $label }}@if ($required)<span class="text-danger" aria-hidden="true"> *</span>@endif</span>
                        @if ($tabHasErrors($fields))<span class="form-tab-error" title="Has errors"><span class="visually-hidden">(has errors)</span></span>@endif
                    </button>
                </li>
            @endforeach
        </ul>

        <div class="tab-content">
            {{-- Personal & contact --}}
            <div @class(['tab-pane', 'fade', 'show active' => $openTab === 'personal']) id="pane-personal" role="tabpanel" aria-labelledby="tab-personal" tabindex="0">
                <div class="card-sies form-pane">
                    <div class="row">
                        <div class="col-md-4"><x-field name="first_name" label="First name" :value="$student->first_name" required maxlength="100" /></div>
                        <div class="col-md-3"><x-field name="middle_name" label="Middle name" :value="$student->middle_name" maxlength="100" /></div>
                        <div class="col-md-3"><x-field name="last_name" label="Last name" :value="$student->last_name" required maxlength="100" /></div>
                        <div class="col-md-2"><x-field name="suffix" label="Suffix" :value="$student->suffix" maxlength="10" placeholder="Jr., III" /></div>
                        <div class="col-md-4">
                            <x-select name="gender" label="Gender" :options="['Male' => 'Male', 'Female' => 'Female', 'Other' => 'Other']"
                                :value="$student->gender" placeholder="Choose" required />
                        </div>
                        <div class="col-md-4"><x-field name="birth_date" type="date" label="Date of birth" :value="$student->birth_date?->toDateString()" required max="{{ today()->subDay()->toDateString() }}" /></div>
                        <div class="col-md-4"><x-field name="previous_school" label="Previous school" :value="$student->previous_school" maxlength="255" /></div>
                    </div>
                    <div class="form-subhead"><x-icon name="phone" :size="15" /> Contact</div>
                    <div class="row">
                        <div class="col-md-6"><x-field name="address" label="Address" :value="$student->address" maxlength="500" placeholder="House no., street, barangay, city" /></div>
                        <div class="col-md-3"><x-field name="contact_number" type="tel" label="Contact number" :value="$student->contact_number" maxlength="20" placeholder="09XX XXX XXXX" /></div>
                        <div class="col-md-3"><x-field name="email" type="email" label="Email address" :value="$student->email" maxlength="255" placeholder="name@example.com" /></div>
                    </div>
                </div>
            </div>

            {{-- Academic --}}
            <div @class(['tab-pane', 'fade', 'show active' => $openTab === 'academic']) id="pane-academic" role="tabpanel" aria-labelledby="tab-academic" tabindex="0">
                <div class="card-sies form-pane">
                    <p class="form-pane-help">Program, year and block section decide which classes fit the student.</p>
                    <div class="row">
                        <div class="col-md-4">
                            <x-select name="program_id" label="Program" :options="$programs->mapWithKeys(fn ($p) => [$p->id => $p->code . ' · ' . $p->name])->all()"
                                :value="$student->program_id" placeholder="Choose a program" required />
                        </div>
                        <div class="col-md-2 col-6">
                            <x-select name="year_level" label="Year level" :options="array_combine(range(1, 6), range(1, 6))" :value="$student->year_level" required />
                        </div>
                        <div class="col-md-2 col-6">
                            <x-field name="section" label="Block section" :value="$student->section" maxlength="20" placeholder="e.g. A" help="A → BSIT 1-A" />
                        </div>
                        <div class="col-md-2 col-6">
                            <x-select name="student_type" label="Student type"
                                :options="['new' => 'New', 'returning' => 'Returning', 'transferee' => 'Transferee']" :value="$student->student_type" required />
                        </div>
                        @if ($editing)
                            <div class="col-md-2 col-6">
                                <x-select name="status" label="Status"
                                    :options="['active' => 'Active', 'inactive' => 'Inactive', 'graduated' => 'Graduated', 'withdrawn' => 'Withdrawn']"
                                    :value="$student->status" required />
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Guardians --}}
            <div @class(['tab-pane', 'fade', 'show active' => $openTab === 'guardians']) id="pane-guardians" role="tabpanel" aria-labelledby="tab-guardians" tabindex="0">
                <div class="card-sies form-pane">
                    <p class="form-pane-help">At least one guardian is required, and one must be the emergency contact.</p>
                    @foreach (array_merge($errors->get('guardians'), ...array_values($errors->get('guardians.*'))) as $message)
                        <div class="alert alert-danger py-2 small">{{ $message }}</div>
                    @endforeach

                    <div class="row g-3">
                        @foreach ($guardianRows as $i => $row)
                            <div class="col-lg-6">
                                <fieldset class="guardian-row h-100">
                                    <legend class="guardian-legend">Guardian {{ $i + 1 }}@if ($i > 0) <span class="text-secondary fw-normal">(optional)</span>@endif</legend>
                                    <div class="row">
                                        <div class="col-md-5">
                                            <label class="form-label" for="g{{ $i }}name">Full name</label>
                                            <input id="g{{ $i }}name" type="text" name="guardians[{{ $i }}][full_name]" maxlength="150" class="form-control mb-3"
                                                value="{{ old("guardians.$i.full_name", $row['full_name']) }}">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label" for="g{{ $i }}rel">Relationship</label>
                                            <input id="g{{ $i }}rel" type="text" name="guardians[{{ $i }}][relationship]" maxlength="50" class="form-control mb-3"
                                                placeholder="e.g. Mother" value="{{ old("guardians.$i.relationship", $row['relationship']) }}">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="g{{ $i }}tel">Contact number</label>
                                            <input id="g{{ $i }}tel" type="tel" name="guardians[{{ $i }}][contact_number]" maxlength="20" class="form-control mb-3"
                                                value="{{ old("guardians.$i.contact_number", $row['contact_number']) }}">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="g{{ $i }}email">Email (optional)</label>
                                            <input id="g{{ $i }}email" type="email" name="guardians[{{ $i }}][email]" maxlength="255" class="form-control mb-3"
                                                placeholder="For absence alerts" value="{{ old("guardians.$i.email", $row['email'] ?? null) }}">
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label" for="g{{ $i }}addr">Address (optional)</label>
                                            <input id="g{{ $i }}addr" type="text" name="guardians[{{ $i }}][address]" maxlength="500" class="form-control mb-3"
                                                value="{{ old("guardians.$i.address", $row['address']) }}">
                                        </div>
                                        <div class="col-md-3 d-flex align-items-end">
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" id="g{{ $i }}em" name="guardians[{{ $i }}][is_emergency_contact]" value="1"
                                                    @checked(session()->hasOldInput() ? (bool) old("guardians.$i.is_emergency_contact") : (bool) $row['is_emergency_contact'])>
                                                <label class="form-check-label small" for="g{{ $i }}em">Emergency contact</label>
                                            </div>
                                        </div>
                                    </div>
                                </fieldset>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Photo --}}
            <div @class(['tab-pane', 'fade', 'show active' => $openTab === 'photo']) id="pane-photo" role="tabpanel" aria-labelledby="tab-photo" tabindex="0">
                <div class="card-sies form-pane">
                    <p class="form-pane-help">Optional. JPG or PNG, up to 2 MB. Photos are stored privately and shown only to staff.</p>
                    <div class="d-flex flex-wrap align-items-start gap-3">
                        @if ($editing && $student->photo_path)
                            <img src="{{ route('students.photo', $student) }}" alt="Current photo of {{ $student->full_name }}" class="student-photo">
                        @endif
                        <div class="flex-grow-1">
                            <x-field name="photo" type="file" :label="$editing && $student->photo_path ? 'Replace photo' : 'Upload photo'" accept="image/png,image/jpeg" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <x-form-actions sticky :submit="$editing ? 'Save changes' : 'Register student'"
            :cancel="$editing ? route('students.show', $student) : route('students.index')">
            <x-slot:lead>
                <div class="d-flex gap-2" data-tab-steps=".form-tabs">
                    <button type="button" class="btn btn-outline-secondary btn-sm-sies" data-tab-step="-1">← Previous</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm-sies" data-tab-step="1">Next →</button>
                </div>
                {{-- Data Privacy Act (RA 10173): consent is recorded once, with the date. --}}
                @if ($editing && $student->privacy_consent_at)
                    <span class="small text-secondary ms-2">Privacy notice agreed {{ $student->privacy_consent_at->format('M j, Y') }}</span>
                @else
                    <div class="form-check mb-0 ms-2 small">
                        <input class="form-check-input @error('privacy_consent') is-invalid @enderror" type="checkbox" name="privacy_consent" value="1" id="privacyConsent" @checked(old('privacy_consent'))>
                        <label class="form-check-label" for="privacyConsent">
                            Student or guardian agreed to the <a href="{{ route('privacy') }}" target="_blank">privacy notice</a>@unless ($editing) <span class="text-danger fw-bold">*</span>@endunless
                        </label>
                        @error('privacy_consent')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                @endif
            </x-slot:lead>
        </x-form-actions>
    </form>
</x-app-layout>
