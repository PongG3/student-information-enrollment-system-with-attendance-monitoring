{{-- The staff data privacy policy. Staff must agree to each published version; the administrator also sees who has. --}}
<x-app-layout title="Data Privacy Policy" subtitle="How staff handle personal data in this system (Data Privacy Act of 2012, RA 10173).">
    @if (auth()->user()->role === 'admin')
        <x-slot:actions>
            <a href="{{ route('privacy-policy.edit') }}" class="btn btn-outline-secondary"><x-icon name="file" :size="16" /> Edit policy</a>
        </x-slot:actions>
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <article class="card-sies p-4 policy-text">
                {!! $html !!}
            </article>
        </div>

        <div class="col-lg-4">
            <aside class="card-sies p-4 policy-status">
                @if (! $published)
                    <x-status-badge value="pending" label="Draft" />
                    <p class="small text-secondary mt-2 mb-0">Not published yet, so staff are not asked to agree. Review the text, then publish it from <strong>Edit policy</strong>.</p>
                @else
                    <div class="small text-secondary">Current version</div>
                    <div class="fw-semibold mb-3">{{ \Carbon\Carbon::parse($version)->format('F j, Y g:i A') }}</div>

                    @if ($agreed !== null)
                        <div class="small text-secondary">Staff who agreed</div>
                        <div class="fw-semibold mb-1">{{ $agreed }} of {{ $staff }}</div>
                        <div class="progress mb-3" style="height: 6px" role="progressbar" aria-label="Staff who agreed" aria-valuenow="{{ $agreed }}" aria-valuemin="0" aria-valuemax="{{ $staff }}">
                            <div class="progress-bar" style="width: {{ $staff ? round($agreed / $staff * 100) : 0 }}%"></div>
                        </div>
                    @endif

                    @if ($agreedAt)
                        <x-status-badge value="verified" label="You agreed" />
                        <div class="small text-secondary mt-1">{{ $agreedAt->format('F j, Y g:i A') }}</div>
                    @elseif ($needsAgreement)
                        <form method="POST" action="{{ route('privacy-policy.agree') }}" novalidate>
                            @csrf
                            <div class="form-check mb-3">
                                <input class="form-check-input @error('agree') is-invalid @enderror" type="checkbox" name="agree" value="1" id="policyAgree">
                                <label class="form-check-label small" for="policyAgree">I have read this policy and will follow it.</label>
                                @error('agree')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <button class="btn btn-navy w-100">I agree</button>
                        </form>
                        <p class="small text-secondary mt-3 mb-0">You need to agree before using the rest of the system. Your agreement is recorded with the date.</p>
                    @endif
                @endif
            </aside>
        </div>
    </div>
</x-app-layout>
