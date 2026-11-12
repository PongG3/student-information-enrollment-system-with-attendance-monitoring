@props(['submit' => 'Save', 'cancel' => null, 'sticky' => false, 'lead' => null])
{{-- Save / Cancel at the end of a form. "sticky" keeps them in view on long forms.
     The "lead" slot (left side) replaces the "required" note, e.g. Previous / Next for tabbed forms. --}}
<div {{ $attributes->class(['form-actionbar', 'is-sticky' => $sticky]) }}>
    @if ($lead)
        {{ $lead }}
    @else
        <span class="small text-secondary d-none d-sm-inline">Fields marked <span class="text-danger fw-bold">*</span> are required.</span>
    @endif
    <div class="form-actions ms-sm-auto">
        @if ($cancel)
            <a href="{{ $cancel }}" class="btn btn-outline-secondary px-4" data-dialog-close>Cancel</a>
        @endif
        {{ $slot }}
        <button type="submit" class="btn btn-navy px-4">{{ $submit }}</button>
    </div>
</div>
