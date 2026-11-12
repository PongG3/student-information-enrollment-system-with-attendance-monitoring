@props(['message', 'icon' => 'search', 'tone' => null])
{{-- Shown when a list has nothing (yet) or nothing matches the filters. An action can go in the slot.
     tone="good" marks good news (e.g. nobody over the absence limit) in green, so it does not read as missing data. --}}
<div {{ $attributes->class(['empty-state', 'is-good' => $tone === 'good']) }}>
    <div class="empty-state-icon" aria-hidden="true"><x-icon :name="$icon" :size="20" /></div>
    <p class="empty-state-text">{{ $message }}</p>
    @if (trim($slot) !== '')
        <div class="empty-state-action">{{ $slot }}</div>
    @endif
</div>
