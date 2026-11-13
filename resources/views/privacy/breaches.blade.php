{{-- Data Privacy (administrator): where the privacy pieces live, and the breach register. --}}
@php
    $dpo = \App\Support\PrivacyContact::line();
    $policyVersion = \App\Support\PrivacyPolicy::version();
@endphp
<x-app-layout title="Data Privacy" subtitle="Data Privacy Act of 2012 (RA 10173): the privacy notice, the staff policy, the Data Protection Officer and the breach register.">
    <x-slot:actions>
        <button type="button" class="btn btn-navy" data-bs-toggle="modal" data-bs-target="#breachModal" data-breach-new>
            <x-icon name="plus" :size="16" /> Record a breach
        </button>
    </x-slot:actions>

    <div class="row g-3 mb-3">
        @foreach ([
            ['Privacy notice', 'What students and guardians are told. Public.', route('privacy'), 'Open'],
            ['Staff policy', $policyVersion ? 'Published ' . \Carbon\Carbon::parse($policyVersion)->format('M j, Y') . '.' : 'Not published yet.', route('privacy-policy.show'), 'Open'],
            ['Data Protection Officer', $dpo ?: 'Not set. Add the name and email in System Settings.', route('settings.edit'), $dpo ? 'Change' : 'Set it'],
            ['Audit log', 'Every sign-in and change is recorded and cannot be edited or deleted in the system. Entries are kept as long as school records are.', route('audit-logs.index'), 'Open'],
        ] as [$title, $text, $url, $action])
            <div class="col-md-6 col-xl-3">
                <div class="card-sies p-3 h-100 d-flex flex-column">
                    <div class="fw-semibold mb-1">{{ $title }}</div>
                    <div class="small text-secondary flex-grow-1">{{ $text }}</div>
                    <a href="{{ $url }}" class="small fw-semibold text-decoration-none mt-2">{{ $action }} →</a>
                </div>
            </div>
        @endforeach
    </div>

    <section class="card-sies" aria-labelledby="breaches-heading">
        <div class="card-header-sies">
            <div>
                <h2 class="h6 fw-bold mb-1" id="breaches-heading">Breach register</h2>
                <div class="small text-secondary">Record every incident where personal data may have been lost, stolen or seen by the wrong people. A serious breach must be reported to the National Privacy Commission within 72 hours.</div>
            </div>
        </div>
        @if ($breaches->isEmpty())
            <x-empty-state icon="shield" tone="good" message="No breaches recorded." />
        @else
            <div class="table-responsive" data-fit-viewport>
                <table class="table table-sies align-middle table-stack mb-0">
                    <thead><tr><th scope="col">Found</th><th scope="col">What happened</th><th scope="col">People</th><th scope="col">NPC told</th><th scope="col">People told</th><th scope="col">Status</th><th scope="col" class="text-end"></th></tr></thead>
                    <tbody>
                        @foreach ($breaches as $b)
                            @php
                                // What the Update window fills its fields with.
                                $edit = [
                                    'action' => route('breaches.update', $b),
                                    'discovered_at' => $b->discovered_at->format('Y-m-d\TH:i'),
                                    'summary' => $b->summary,
                                    'data_affected' => $b->data_affected,
                                    'people_affected' => $b->people_affected,
                                    'actions_taken' => $b->actions_taken,
                                    'npc_notified_on' => $b->npc_notified_on?->toDateString(),
                                    'subjects_notified_on' => $b->subjects_notified_on?->toDateString(),
                                    'closed' => $b->status === 'closed',
                                ];
                            @endphp
                            <tr>
                                <td class="text-nowrap" data-label="Found">{{ $b->discovered_at->format('M j, Y g:i A') }}</td>
                                <td data-label="What">
                                    <div class="fw-semibold">{{ $b->summary }}</div>
                                    @if ($b->data_affected)<div class="small text-secondary">{{ \Illuminate\Support\Str::limit($b->data_affected, 90) }}</div>@endif
                                </td>
                                <td data-label="People">{{ $b->people_affected ?? '—' }}</td>
                                <td data-label="NPC told" class="small">
                                    @if ($b->npc_notified_on)
                                        {{ $b->npc_notified_on->format('M j, Y') }}
                                    @elseif ($b->status === 'open')
                                        <span @class(['text-danger fw-semibold' => now()->gt($b->npcDeadline())])>Due by {{ $b->npcDeadline()->format('M j, g:i A') }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td data-label="People told" class="small">{{ $b->subjects_notified_on?->format('M j, Y') ?? '—' }}</td>
                                <td data-label="Status"><x-status-badge :value="$b->status === 'open' ? 'pending' : 'closed'" :label="ucfirst($b->status)" /></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-outline-secondary btn-sm-sies" data-bs-toggle="modal" data-bs-target="#breachModal"
                                        data-breach="{{ json_encode($edit) }}">Update</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-table-footer :items="$breaches" noun="breach" />
        @endif
    </section>

    {{-- One window for recording a new breach and updating one. --}}
    <div class="modal fade" id="breachModal" tabindex="-1" aria-labelledby="breachTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-fullscreen-sm-down">
            <form method="POST" action="{{ route('breaches.store') }}" class="modal-content" novalidate data-breach-form>
                @csrf
                <input type="hidden" name="_method" value="POST" data-breach-method>
                <div class="modal-header">
                    <h2 class="modal-title h5 fw-bold" id="breachTitle">Record a breach</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label" for="bFound">Found on <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="discovered_at" id="bFound" class="form-control @error('discovered_at') is-invalid @enderror" value="{{ old('discovered_at', now()->format('Y-m-d\TH:i')) }}">
                            @error('discovered_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-7">
                            <label class="form-label" for="bSummary">What happened <span class="text-danger">*</span></label>
                            <input type="text" name="summary" id="bSummary" maxlength="255" class="form-control @error('summary') is-invalid @enderror" value="{{ old('summary') }}" placeholder="e.g. A printed class list was left in the library">
                            @error('summary')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="bData">Data affected</label>
                            <input type="text" name="data_affected" id="bData" maxlength="2000" class="form-control" value="{{ old('data_affected') }}" placeholder="e.g. names, student IDs, guardian phone numbers">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="bPeople">People affected</label>
                            <input type="number" name="people_affected" id="bPeople" min="0" class="form-control" value="{{ old('people_affected') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="bActions">What was done</label>
                            <textarea name="actions_taken" id="bActions" rows="3" maxlength="4000" class="form-control excuse-reason">{{ old('actions_taken') }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="bNpc">National Privacy Commission told on</label>
                            <input type="date" name="npc_notified_on" id="bNpc" class="form-control" value="{{ old('npc_notified_on') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="bSubjects">People affected told on</label>
                            <input type="date" name="subjects_notified_on" id="bSubjects" class="form-control" value="{{ old('subjects_notified_on') }}">
                        </div>
                        <div class="col-12" data-breach-closed-row hidden>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="closed" value="1" id="bClosed">
                                <label class="form-check-label" for="bClosed">Closed: nothing more to do</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-navy px-4">Save</button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const modal = document.getElementById('breachModal');
                const form = modal.querySelector('[data-breach-form]');
                modal.addEventListener('show.bs.modal', (event) => {
                    const button = event.relatedTarget;
                    if (!button) return;   // reopened after an error
                    const data = button.dataset.breach ? JSON.parse(button.dataset.breach) : null;
                    form.reset();
                    form.action = data ? data.action : @json(route('breaches.store'));
                    form.querySelector('[data-breach-method]').value = data ? 'PUT' : 'POST';
                    modal.querySelector('#breachTitle').textContent = data ? 'Update breach record' : 'Record a breach';
                    modal.querySelector('[data-breach-closed-row]').hidden = !data;
                    if (data) {
                        for (const [name, value] of Object.entries(data)) {
                            const field = form.elements[name];
                            if (!field || name === 'action') continue;
                            if (field.type === 'checkbox') field.checked = !!value; else field.value = value ?? '';
                        }
                    }
                });
                @if ($errors->any())
                    bootstrap.Modal.getOrCreateInstance(modal).show();
                @endif
            });
        </script>
    @endpush
</x-app-layout>
