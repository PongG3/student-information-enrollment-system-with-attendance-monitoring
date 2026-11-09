@props(['name', 'label', 'options', 'value' => null, 'required' => false, 'placeholder' => null, 'id' => null, 'help' => null])
@php($id = $id ?? $name)
<div class="mb-3">
    <label for="{{ $id }}" class="form-label fw-semibold small">
        {{ $label }}@if ($required) <span class="text-danger">*</span>@endif
    </label>
    <select id="{{ $id }}" name="{{ $name }}"
        {{ $attributes->class(['form-select', 'is-invalid' => $errors->has($name)]) }}>
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) old($name, $value) === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @if ($help)
        <div class="form-text">{{ $help }}</div>
    @endif
    @error($name)
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>
