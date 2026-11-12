{{-- The administrator writes the policy (for the Data Protection Officer) and publishes it. --}}
<x-app-layout title="Edit Data Privacy Policy" subtitle="Write the rules staff follow. Publishing asks every staff member to agree again."
    :back="route('privacy-policy.show')" back-label="Back to the policy">
    <form method="POST" action="{{ route('privacy-policy.update') }}" novalidate>
        @csrf
        @method('PUT')
        <div class="row g-3">
            <div class="col-lg-8">
                <section class="card-sies p-4">
                    <label for="policyText" class="form-label fw-semibold">Policy text</label>
                    <textarea name="text" id="policyText" rows="18" class="form-control font-monospace small @error('text') is-invalid @enderror">{{ old('text', $text) }}</textarea>
                    @error('text')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </section>
            </div>
            <div class="col-lg-4">
                <aside class="card-sies p-4 small">
                    <div class="fw-semibold mb-1">Status</div>
                    @if ($published)
                        <p class="text-secondary">Published {{ \Carbon\Carbon::parse($version)->format('F j, Y g:i A') }}.</p>
                    @else
                        <p class="text-secondary">Draft. Staff are not asked to agree until you publish.</p>
                    @endif

                    <div class="fw-semibold mb-1">Formatting</div>
                    <ul class="text-secondary ps-3">
                        <li><code>## Heading</code> starts a section</li>
                        <li><code>- item</code> makes a bullet</li>
                        <li>A blank line starts a new paragraph</li>
                    </ul>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="publish" value="1" id="policyPublish" @checked(! $published)>
                        <label class="form-check-label" for="policyPublish">
                            {{ $published ? 'Publish as a new version (everyone agrees again)' : 'Publish now' }}
                        </label>
                    </div>
                    <button class="btn btn-navy w-100">Save</button>
                    <p class="text-secondary mt-3 mb-0">Small fixes such as typos can be saved without publishing.</p>
                </aside>
            </div>
        </div>
    </form>
</x-app-layout>
