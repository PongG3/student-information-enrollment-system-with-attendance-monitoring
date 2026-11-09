{{-- Where the registrar is in the two-step "new request" flow. --}}
<ol class="stepper mb-3" aria-label="Steps">
    @foreach ([1 => 'Find the student', 2 => 'Choose classes'] as $number => $label)
        <li @class(['stepper-step', 'is-done' => $step > $number, 'is-current' => $step === $number]) @if ($step === $number) aria-current="step" @endif>
            <span class="stepper-number">@if ($step > $number)✓@else{{ $number }}@endif</span>
            <span class="stepper-label">{{ $label }}</span>
        </li>
    @endforeach
</ol>
