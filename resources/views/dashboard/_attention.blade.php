{{-- "Needs attention": one row of chips. Needs: $attention.
     More than four: the first four show, the rest behind "+N more", so the bar stays one line. --}}
@php $shown = 4; @endphp
@if (count($attention) > 0)
    <div class="attention-bar mb-3" role="region" aria-label="Needs attention">
        <h2 class="attention-title"><x-icon name="alert" :size="16" /> Needs attention</h2>
        @foreach ($attention as $i => $item)
            <a href="{{ $item['url'] }}" class="attention-item" @if ($i >= $shown) hidden data-attention-more @endif>
                <span class="attention-count is-{{ $item['tone'] }}">{{ $item['count'] }}</span>
                <span>{{ $item['label'] }}</span>
                <span class="attention-go" aria-hidden="true"><x-icon name="arrow" :size="14" /></span>
            </a>
        @endforeach
        @if (count($attention) > $shown)
            <button type="button" class="attention-more" aria-expanded="false"
                onclick="this.parentElement.querySelectorAll('[data-attention-more]').forEach((el) => el.hidden = false); this.remove();">
                +{{ count($attention) - $shown }} more
            </button>
        @endif
    </div>
@endif
