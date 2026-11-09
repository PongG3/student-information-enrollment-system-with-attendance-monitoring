@props(['title', 'subtitle' => null, 'back' => null, 'backLabel' => 'Back', 'centered' => false])
@php($crumbs = auth()->check() ? \App\Support\Navigation::breadcrumbs(auth()->user(), $title) : [])
<div @class(['page-header', 'page-header-centered' => $centered])>
    @if ($crumbs || $back)
        <div class="page-crumbs">
            @if ($crumbs)
                <nav aria-label="Breadcrumb">
                    <ol class="breadcrumb-sies">
                        @foreach ($crumbs as $crumb)
                            <li>
                                @if ($crumb['url'])
                                    <a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a>
                                @else
                                    <span aria-current="page">{{ $crumb['label'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </nav>
            @endif
            @if ($back)
                <a href="{{ $back }}" class="back-link"><span aria-hidden="true">←</span> {{ $backLabel }}</a>
            @endif
        </div>
    @endif
    <h1 class="page-title">{{ $title }}</h1>
    @if ($subtitle)
        <p class="page-subtitle">{{ $subtitle }}</p>
    @endif
</div>
