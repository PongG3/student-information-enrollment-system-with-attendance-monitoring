@props(['title', 'help' => null, 'icon' => null])
{{-- One titled part of a long form (Personal information, Contact, ...). --}}
<section {{ $attributes->class(['card-sies', 'p-4', 'mb-4']) }}>
    <div class="section-head">
        @if ($icon)
            <span class="section-icon" aria-hidden="true"><x-icon :name="$icon" :size="18" /></span>
        @endif
        <div>
            <h2 class="section-title">{{ $title }}</h2>
            @if ($help)
                <p class="section-help">{{ $help }}</p>
            @endif
        </div>
    </div>
    {{ $slot }}
</section>
