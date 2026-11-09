@props(['message', 'icon' => 'search'])
{{-- Shown when a list has nothing (yet) or nothing matches the filters. An action can go in the slot. --}}
<div {{ $attributes->class(['empty-state']) }}>
    <div class="empty-state-icon" aria-hidden="true"><x-icon :name="$icon" :size="20" /></div>
    <p class="empty-state-text">{{ $message }}</p>
    @if (trim($slot) !== '')
        <div class="empty-state-action">{{ $slot }}</div>
    @endif
</div>
