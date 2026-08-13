@if ($paginator->hasPages())
<div class="parts-pag-btns">
    {{-- Prev --}}
    @if ($paginator->onFirstPage())
        <button type="button" disabled>&lsaquo; Prev</button>
    @else
        <a href="{{ $paginator->previousPageUrl() }}" rel="prev">&lsaquo; Prev</a>
    @endif

    @php
        $window = 2;
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();
        $start = max(1, $current - $window);
        $end = min($last, $current + $window);
    @endphp

    {{-- Leading page(s) + ellipsis --}}
    @if ($start > 1)
        <a href="{{ $paginator->url(1) }}">1</a>
        @if ($start > 2)<span class="p-ellipsis">&hellip;</span>@endif
    @endif

    {{-- Window --}}
    @for ($i = $start; $i <= $end; $i++)
        @if ($i === $current)
            <span class="active">{{ $i }}</span>
        @else
            <a href="{{ $paginator->url($i) }}">{{ $i }}</a>
        @endif
    @endfor

    {{-- Trailing ellipsis + last page --}}
    @if ($end < $last)
        @if ($end < $last - 1)<span class="p-ellipsis">&hellip;</span>@endif
        <a href="{{ $paginator->url($last) }}">{{ $last }}</a>
    @endif

    {{-- Next --}}
    @if ($paginator->hasMorePages())
        <a href="{{ $paginator->nextPageUrl() }}" rel="next">Next &rsaquo;</a>
    @else
        <button type="button" disabled>Next &rsaquo;</button>
    @endif
</div>
@endif