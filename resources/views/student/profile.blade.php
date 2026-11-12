<x-app-layout title="My Profile" subtitle="Your record at the registrar's office. You can update your contact details and send a new photo; ask the registrar to correct anything else.">
    @php
        // Groups: [heading, locked by the registrar?, [[label, value, full width], ...]]
        $groups = [
            ['Academic status', true, [
                ['Program', $student->program->code . ' · ' . $student->program->name, true],
                ['Year level / type', 'Year ' . $student->year_level . ' · ' . ucfirst($student->student_type), false],
                ['Block section', $student->block_section ?? 'Not assigned', false],
            ]],
            ['Personal information', true, [
                ['Gender', $student->gender, false],
                ['Date of birth', $student->birth_date->format('M j, Y'), false],
                ['Previous school', $student->previous_school ?: '—', true],
            ]],
            ['Contact details', false, [
                ['Email', $student->email ?: '—', false],
                ['Contact number', $student->contact_number ?: '—', false],
                ['Address', $student->address ?: '—', true],
            ]],
        ];
        $initials = strtoupper(substr($student->first_name, 0, 1) . substr($student->last_name, 0, 1));
        $lockTip = 'Kept by the registrar. Ask the registrar\'s office to correct it.';
    @endphp
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card-sies p-4 h-100">
                <div class="profile-head">
                    {{-- Photo: the official one (or initials), with a button to send a new one for approval. --}}
                    <div class="profile-photo-wrap">
                        @if ($student->photo_path)
                            <img src="{{ route('student.photo') }}" alt="Your official photo" class="profile-avatar profile-photo">
                        @else
                            <div class="profile-avatar" aria-hidden="true">{{ $initials }}</div>
                        @endif
                        <button type="button" class="profile-photo-edit" data-bs-toggle="modal" data-bs-target="#photoModal"
                                aria-label="Send a new photo" title="Send a new photo"><x-icon name="camera" :size="14" /></button>
                    </div>
                    <div class="min-w-0">
                        <h2 class="h5 fw-bold mb-1">{{ $student->full_name }}</h2>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="text-secondary small">{{ $student->student_number }}</span>
                            <x-status-badge :value="$student->status" />
                        </div>
                        @if ($student->pending_photo_path)
                            <div class="small mt-1"><x-status-badge value="pending" label="New photo waiting for approval" /></div>
                        @endif
                    </div>
                </div>

                @foreach ($groups as [$heading, $locked, $fields])
                    <section class="profile-group" aria-label="{{ $heading }}">
                        <h3 class="profile-group-title">
                            {{ $heading }}
                            @if ($locked)
                                <span class="profile-lock" title="{{ $lockTip }}" tabindex="0" data-bs-toggle="tooltip">
                                    <x-icon name="lock" :size="12" /><span class="visually-hidden">{{ $lockTip }}</span>
                                </span>
                            @else
                                <button type="button" class="btn btn-link btn-sm p-0 ms-auto profile-edit" data-bs-toggle="modal" data-bs-target="#contactModal">
                                    <x-icon name="pencil" :size="13" /> Edit
                                </button>
                            @endif
                        </h3>
                        <dl class="profile-details">
                            @foreach ($fields as [$label, $value, $wide])
                                <div @class(['is-wide' => $wide])>
                                    <dt>{{ $label }}</dt>
                                    <dd>{{ $value }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </section>
                @endforeach
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card-sies mb-4">
                <div class="card-header-sies"><h2 class="h6 fw-bold mb-0">Parents / guardians</h2></div>
                @if ($student->guardians->isEmpty())
                    <x-empty-state icon="students" message="No guardian recorded." />
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
                    <x-empty-state icon="file" message="No requirements are set up." />
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

    {{-- Contact details: the student updates these directly (the change is recorded). --}}
    <div class="modal fade" id="contactModal" tabindex="-1" aria-labelledby="contactTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('student.profile.contact') }}" class="modal-content" novalidate>
                @csrf
                @method('PUT')
                <input type="hidden" name="_contact" value="1">
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title h5 fw-bold" id="contactTitle">Contact details</h2>
                        <div class="small text-secondary">So the school can reach you. Saved right away.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label" for="contactEmail">Email</label>
                    <input type="email" name="email" id="contactEmail" maxlength="255" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $student->email) }}">
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror

                    <label class="form-label mt-3" for="contactNumber">Contact number</label>
                    <input type="tel" name="contact_number" id="contactNumber" maxlength="20" class="form-control @error('contact_number') is-invalid @enderror" value="{{ old('contact_number', $student->contact_number) }}" placeholder="e.g. 0917 123 4567">
                    @error('contact_number')<div class="invalid-feedback">{{ $message }}</div>@enderror

                    <label class="form-label mt-3" for="contactAddress">Address</label>
                    <textarea name="address" id="contactAddress" rows="2" maxlength="500" class="form-control excuse-reason @error('address') is-invalid @enderror">{{ old('address', $student->address) }}</textarea>
                    @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-navy px-4">Save</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Photo: sent to the registrar, who approves it as the official ID photo. --}}
    <div class="modal fade" id="photoModal" tabindex="-1" aria-labelledby="photoTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title h5 fw-bold" id="photoTitle">Profile photo</h2>
                        <div class="small text-secondary">The registrar approves it before it becomes your official photo.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @if ($student->pending_photo_path)
                        <div class="photo-review mb-3">
                            <img src="{{ route('student.photo', ['pending' => 1]) }}" alt="The photo you sent" class="photo-review-img">
                            <div class="small">
                                <div class="fw-semibold">Waiting for approval</div>
                                <div class="text-secondary mb-2">Sent {{ $student->pending_photo_at?->format('M j, Y g:i A') }}. Sending another replaces it.</div>
                                <form method="POST" action="{{ route('student.profile.photo.cancel') }}">@csrf @method('DELETE')
                                    <button class="btn btn-quiet-danger btn-sm-sies">Withdraw it</button>
                                </form>
                            </div>
                        </div>
                    @endif
                    <form method="POST" action="{{ route('student.profile.photo') }}" enctype="multipart/form-data" id="photoForm" novalidate>
                        @csrf
                        <x-file-drop name="photo" id="photoFile" accept=".jpg,.jpeg,.png" hint="JPG or PNG, up to 2 MB, at least 200 × 200 pixels" :max-mb="2" />
                    </form>
                    <ul class="small text-secondary mt-3 mb-0 ps-3">
                        <li>A recent, formal photo: face clearly visible, plain light background.</li>
                        <li>No filters, sunglasses or group photos. It is used to recognize you in class.</li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="photoForm" class="btn btn-navy px-4">Send for approval</button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                // Locked fields explain themselves on hover or focus.
                document.querySelectorAll('.profile-lock[data-bs-toggle="tooltip"]').forEach((el) => bootstrap.Tooltip.getOrCreateInstance(el));
                // Reopen the dialog that had an error.
                @if ($errors->hasAny(['email', 'contact_number', 'address']))
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('contactModal')).show();
                @elseif ($errors->has('photo'))
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('photoModal')).show();
                @endif
            });
        </script>
    @endpush
</x-app-layout>
