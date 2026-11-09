@props(['items', 'noun' => 'record'])
{{-- Under every paginated table: how many rows, and page links when there is more than one page. --}}
@if ($items->total() > 0)
    <div class="pagination-bar">
        <p class="small text-secondary">
            Showing <strong class="text-navy">{{ number_format($items->firstItem()) }}–{{ number_format($items->lastItem()) }}</strong>
            of <strong class="text-navy">{{ number_format($items->total()) }}</strong> {{ \Illuminate\Support\Str::plural($noun, $items->total()) }}
        </p>
        @if ($items->hasPages())
            {{ $items->onEachSide(1)->links('components.pagination-links') }}
        @endif
    </div>
@endif
