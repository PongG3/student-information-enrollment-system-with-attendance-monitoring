@props(['name', 'label', 'type' => 'text', 'value' => null, 'required' => false, 'help' => null, 'id' => null])
@php($id = $id ?? $name)
<div class="mb-3">
    <label for="{{ $id }}" class="form-label fw-semibold small">
        {{ $label }}@if ($required) <span class="text-danger">*</span>@endif
    </label>
    @if ($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" rows="3"
            {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($name)]) }}>{{ old($name, $value) }}</textarea>
    @else
        <input id="{{ $id }}" type="{{ $type }}" name="{{ $name }}"
            value="{{ $type === 'password' ? '' : old($name, $value) }}"
            {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($name)]) }}>
    @endif
    @if ($help)
        <div class="form-text">{{ $help }}</div>
    @endif
    @error($name)
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>
