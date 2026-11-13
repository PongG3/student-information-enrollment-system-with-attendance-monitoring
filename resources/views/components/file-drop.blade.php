@props(['name' => 'file', 'id' => null, 'accept' => '', 'hint' => '', 'maxMb' => 5, 'required' => false, 'requiredMessage' => 'Choose a file to upload first.'])
{{-- A file field as a dashed drop box: drag a file onto it or click to browse. Once chosen, the file shows as a
     chip (name and size) with × to remove it. It is still a normal <input type="file">, so forms work without JavaScript. --}}
@php $id ??= 'file-' . $name; @endphp
<div class="file-drop" data-file-drop data-max-mb="{{ $maxMb }}" @if ($required) data-required="{{ $requiredMessage }}" @endif>
    <input type="file" name="{{ $name }}" id="{{ $id }}" accept="{{ $accept }}"
        {{ $attributes->class(['file-drop-input', 'is-invalid' => $errors->has($name)]) }}>
    <label for="{{ $id }}" class="file-drop-zone" data-file-drop-zone>
        <x-icon name="file" :size="20" />
        <span><strong>Drag &amp; drop</strong> a file here, or <span class="file-drop-browse">browse</span></span>
        @if ($hint)<small class="text-secondary">{{ $hint }}</small>@endif
    </label>
    <div class="file-chip" data-file-drop-chip hidden>
        <x-icon name="file" :size="18" />
        <div class="min-w-0">
            <div class="file-chip-name text-truncate" data-file-drop-name></div>
            <div class="small text-secondary" data-file-drop-size></div>
        </div>
        <button type="button" class="file-chip-remove" data-file-drop-remove aria-label="Remove this file">×</button>
    </div>
    <div class="invalid-feedback d-block" data-file-drop-error @unless ($errors->has($name)) hidden @endunless>{{ $errors->first($name) }}</div>
</div>
