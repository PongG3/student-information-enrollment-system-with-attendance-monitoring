{{-- "Needs attention": one row of chips. Needs: $attention. --}}
@if (count($attention) > 0)
    <div class="attention-bar mb-3" role="region" aria-label="Needs attention">
        <h2 class="attention-title"><x-icon name="alert" :size="16" /> Needs attention</h2>
        @foreach ($attention as $item)
            <a href="{{ $item['url'] }}" class="attention-item">
                <span class="attention-count is-{{ $item['tone'] }}">{{ $item['count'] }}</span>
                <span>{{ $item['label'] }}</span>
                <span class="attention-go" aria-hidden="true"><x-icon name="arrow" :size="14" /></span>
            </a>
        @endforeach
    </div>
@endif
