@props(['submit' => 'Save', 'cancel' => null, 'sticky' => false])
{{-- Save / Cancel at the end of a form. "sticky" keeps them in view on long forms. --}}
<div {{ $attributes->class(['form-actionbar', 'is-sticky' => $sticky]) }}>
    <span class="small text-secondary d-none d-sm-inline">Fields marked <span class="text-danger fw-bold">*</span> are required.</span>
    <div class="form-actions ms-sm-auto">
        @if ($cancel)
            <a href="{{ $cancel }}" class="btn btn-outline-secondary px-4">Cancel</a>
        @endif
        {{ $slot }}
        <button type="submit" class="btn btn-navy px-4">{{ $submit }}</button>
    </div>
</div>
