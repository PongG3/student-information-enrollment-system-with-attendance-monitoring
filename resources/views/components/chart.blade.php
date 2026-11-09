@props(['title', 'config', 'empty' => false, 'emptyMessage' => 'No data yet.', 'height' => 260, 'note' => null])
<div class="card-sies h-100">
    <div class="card-header-sies">
        <h2 class="h6 fw-bold mb-0">{{ $title }}</h2>
        @if ($note)<span class="small text-secondary">{{ $note }}</span>@endif
    </div>
    @if ($empty)
        <x-empty-state :message="$emptyMessage" />
    @else
        <div class="p-3">
            <div style="position: relative; height: {{ $height }}px">
                <canvas role="img" aria-label="{{ $title }}" data-chart="{{ json_encode($config) }}">{{ $title }}</canvas>
            </div>
        </div>
    @endif
</div>
