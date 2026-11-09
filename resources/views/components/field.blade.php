@props(['name', 'label', 'type' => 'text', 'value' => null, 'required' => false, 'help' => null, 'id' => null, 'suffix' => null])
@php($id = $id ?? $name)
<div class="mb-3">
    <label for="{{ $id }}" class="form-label fw-semibold small">
        {{ $label }}@if ($required) <span class="text-danger">*</span>@endif
    </label>
    @if ($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" rows="3"
            {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($name)]) }}>{{ old($name, $value) }}</textarea>
    @else
        @if ($suffix)<div class="input-group">@endif
        <input id="{{ $id }}" type="{{ $type }}" name="{{ $name }}"
            value="{{ $type === 'password' ? '' : old($name, $value) }}"
            @if ($help) aria-describedby="{{ $id }}-help" @endif
            {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($name)]) }}>
        @if ($suffix)<span class="input-group-text">{{ $suffix }}</span></div>@endif
    @endif
    @if ($help)
        <div class="form-text" id="{{ $id }}-help">{{ $help }}</div>
    @endif
    @error($name)
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>
