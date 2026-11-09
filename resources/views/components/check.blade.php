@props(['name', 'label', 'checked' => false, 'id' => null, 'help' => null])
@php
    $id = $id ?? $name;
    $isChecked = session()->hasOldInput() ? (bool) old($name) : (bool) $checked;
@endphp
<div class="mb-3">
    <div class="form-check">
        <input class="form-check-input" type="checkbox" id="{{ $id }}" name="{{ $name }}" value="1" @checked($isChecked)>
        <label class="form-check-label" for="{{ $id }}">{{ $label }}</label>
    </div>
    @if ($help)
        <div class="form-text">{{ $help }}</div>
    @endif
</div>
