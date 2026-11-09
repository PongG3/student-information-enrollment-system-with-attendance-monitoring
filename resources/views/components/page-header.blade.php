@props(['title', 'subtitle' => null, 'back' => null, 'backLabel' => 'Back', 'centered' => false])
<div @class(['mb-4', 'page-header-centered' => $centered])>
    @if ($back)
        <a href="{{ $back }}" class="back-link"><span aria-hidden="true">←</span> {{ $backLabel }}</a>
    @endif
    <h1 class="page-title">{{ $title }}</h1>
    @if ($subtitle)
        <p class="page-subtitle">{{ $subtitle }}</p>
    @endif
</div>
