{{-- My Profile for staff. Name, email and role come from User Management (locked); department, office,
     contact number and photo are the staff member's own. Teachers also see this term's teaching load. --}}
@php
    $roleLabel = ['admin' => 'Administrator', 'registrar' => 'Registrar', 'teacher' => 'Teacher'][$user->role] ?? ucfirst($user->role);
    $lockTip = 'Kept in User Management. Ask an administrator to change it.';
    $groups = [
        ['Account', true, [
            ['Name', $user->name, true],
            ['Email (sign-in)', $user->email, true],
            ['Role', $roleLabel, false],
            ['Member since', $user->created_at?->format('M j, Y') ?? '—', false],
        ]],
        ['Work details', false, [
            ['Department / specialization', $user->department ?: 'Not set', true],
            ['Office', $user->office ?: 'Not set', false],
            ['Contact number', $user->contact_number ?: 'Not set', false],
        ]],
    ];
@endphp
<x-app-layout title="My Profile" subtitle="Your staff profile. Keep your work details and photo up to date; your account details are managed by an administrator.">
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card-sies p-4 h-100">
                <div class="profile-head">
                    <div class="profile-photo-wrap">
                        @if ($user->photoUrl())
                            <img src="{{ $user->photoUrl() }}" alt="Your photo" class="profile-avatar profile-photo">
                        @else
                            <div class="profile-avatar" aria-hidden="true">{{ $user->initials() }}</div>
                        @endif
                        <button type="button" class="profile-photo-edit" data-bs-toggle="modal" data-bs-target="#photoModal"
                                aria-label="Change your photo" title="Change your photo"><x-icon name="camera" :size="14" /></button>
                    </div>
                    <div class="min-w-0">
                        <h2 class="h5 fw-bold mb-1">{{ $user->name }}</h2>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <x-status-badge value="current" :label="$roleLabel" />
                            @if ($user->department)<span class="small text-secondary">{{ $user->department }}</span>@endif
                        </div>
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
                                <button type="button" class="btn btn-link btn-sm p-0 ms-auto profile-edit" data-bs-toggle="modal" data-bs-target="#detailsModal">
                                    <x-icon name="pencil" :size="13" /> Edit
                                </button>
                            @endif
                        </h3>
                        <dl class="profile-details">
                            @foreach ($fields as [$label, $value, $wide])
                                <div @class(['is-wide' => $wide])>
                                    <dt>{{ $label }}</dt>
                                    <dd @class(['text-secondary' => $value === 'Not set'])>{{ $value }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </section>
                @endforeach
                @if ($user->role === 'teacher')
                    <p class="small text-secondary mt-3 mb-0">Students see your name, photo and department. Your contact number is shown to staff only.</p>
                @endif
            </div>
        </div>

        <div class="col-lg-7">
            @if ($teaching !== null)
                <div class="card-sies mb-4">
                    <div class="card-header-sies">
                        <h2 class="h6 fw-bold mb-0">This term</h2>
                        <a href="{{ route('teacher.classes') }}" class="small fw-semibold text-decoration-none">My classes →</a>
                    </div>
                    <div class="row g-0 text-center border-bottom">
                        @foreach ([['Classes', $teaching['classes']->count()], ['Students', $teaching['students']], ['Hours a week', rtrim(rtrim(number_format($teaching['hours'], 1), '0'), '.')]] as [$label, $value])
                            <div class="col-4 py-3">
                                <div class="fs-4 fw-bold text-navy">{{ $value }}</div>
                                <div class="small text-secondary">{{ $label }}</div>
                            </div>
                        @endforeach
                    </div>
                    @if ($teaching['classes']->isEmpty())
                        <x-empty-state icon="classes" message="No classes assigned to you this term." />
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach ($teaching['classes'] as $class)
                                <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                                    <div class="min-w-0">
                                        <a href="{{ route('classes.show', $class) }}" class="fw-semibold text-decoration-none">{{ $class->subject->code }}</a>
                                        <span class="small text-secondary">· {{ $class->subject->title }} · {{ $class->block_label }}</span>
                                        <div class="small text-secondary">{{ $class->scheduleSummary() }}</div>
                                    </div>
                                    <span class="small text-nowrap">{{ $class->enrolled_count }} {{ \Illuminate\Support\Str::plural('student', $class->enrolled_count) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

            <div class="card-sies">
                <div class="card-header-sies"><h2 class="h6 fw-bold mb-0">Your recent activity</h2></div>
                @if ($activity->isEmpty())
                    <x-empty-state message="Nothing recorded yet." />
                @else
                    <ul class="activity-list">
                        @foreach ($activity as $log)
                            <li>
                                <div class="activity-text">{{ $log->description }}</div>
                                <div class="activity-meta"><time datetime="{{ $log->created_at->toIso8601String() }}" title="{{ $log->created_at->format('M j, Y g:i A') }}">{{ $log->created_at->diffForHumans() }}</time></div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    {{-- Work details --}}
    <div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('profile.update') }}" class="modal-content" novalidate>
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title h5 fw-bold" id="detailsTitle">Work details</h2>
                        <div class="small text-secondary">Saved right away.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label" for="profileDepartment">Department / specialization</label>
                    <input type="text" name="department" id="profileDepartment" maxlength="120" class="form-control @error('department') is-invalid @enderror"
                           value="{{ old('department', $user->department) }}" placeholder="e.g. Computer Science">
                    @error('department')<div class="invalid-feedback">{{ $message }}</div>@enderror

                    <label class="form-label mt-3" for="profileOffice">Office</label>
                    <input type="text" name="office" id="profileOffice" maxlength="120" class="form-control @error('office') is-invalid @enderror"
                           value="{{ old('office', $user->office) }}" placeholder="e.g. Faculty Room 2">
                    @error('office')<div class="invalid-feedback">{{ $message }}</div>@enderror

                    <label class="form-label mt-3" for="profileContact">Contact number <span class="text-secondary fw-normal small">(staff only)</span></label>
                    <input type="tel" name="contact_number" id="profileContact" maxlength="20" class="form-control @error('contact_number') is-invalid @enderror"
                           value="{{ old('contact_number', $user->contact_number) }}" placeholder="e.g. 0917 123 4567">
                    @error('contact_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-navy px-4">Save</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Photo: framed with the same cropper as students; no approval needed for staff. --}}
    <div class="modal fade" id="photoModal" tabindex="-1" aria-labelledby="photoTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title h5 fw-bold" id="photoTitle">Profile photo</h2>
                        <div class="small text-secondary">Shown in the top bar{{ $user->role === 'teacher' ? ' and to your students' : '' }}.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="{{ route('profile.photo') }}" enctype="multipart/form-data" id="photoForm" novalidate>
                        @csrf
                        <div data-photo-crop>
                            <x-file-drop name="photo" id="photoFile" accept=".jpg,.jpeg,.png" hint="JPG or PNG, at least 200 × 200 pixels. You can frame it next." :max-mb="15" />
                            <div class="crop-stage" data-crop-stage hidden>
                                <div class="crop-canvas"><img alt="Your photo, to frame" data-crop-image></div>
                                <div class="crop-tools">
                                    <button type="button" class="crop-btn" data-crop-zoom-out aria-label="Zoom out">−</button>
                                    <input type="range" class="form-range" min="1" max="3" step="0.01" value="1" data-crop-zoom aria-label="Zoom">
                                    <button type="button" class="crop-btn" data-crop-zoom-in aria-label="Zoom in">+</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm-sies ms-2" data-crop-rotate>↻ Rotate</button>
                                </div>
                                <div class="crop-file">
                                    <x-icon name="file" :size="16" />
                                    <span class="text-truncate" data-crop-name></span>
                                    <button type="button" class="btn btn-link btn-sm p-0 ms-auto text-nowrap" data-crop-change>Change photo</button>
                                    <button type="button" class="file-chip-remove" data-crop-remove aria-label="Remove this photo">×</button>
                                </div>
                            </div>
                        </div>
                    </form>
                    <p class="small text-secondary mt-3 mb-0">A clear, professional photo with your face in the circle.</p>
                </div>
                <div class="modal-footer">
                    @if ($user->photo_path)
                        <form method="POST" action="{{ route('profile.photo.remove') }}" class="me-auto">@csrf @method('DELETE')
                            <button class="btn btn-quiet-danger btn-sm-sies">Remove current photo</button>
                        </form>
                    @endif
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="photoForm" class="btn btn-navy px-4">Save photo</button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                document.querySelectorAll('.profile-lock[data-bs-toggle="tooltip"]').forEach((el) => bootstrap.Tooltip.getOrCreateInstance(el));
                @if ($errors->hasAny(['department', 'office', 'contact_number']))
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('detailsModal')).show();
                @elseif ($errors->has('photo'))
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('photoModal')).show();
                @endif
            });
        </script>
    @endpush
</x-app-layout>
