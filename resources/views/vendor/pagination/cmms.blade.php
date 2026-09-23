{{-- D9.40 / D9.41 - Shared CMMS pagination bar.
     Rendered from every list page via $paginator->links('vendor.pagination.cmms')
     (pinned as the paginator default view in AppServiceProvider) so the whole system
     shares one look. The result count is always shown while there are rows - kaya ang
     server-side filter ay may agarang feedback - at ang windowed page numbers ay
     lumalabas lamang kapag higit sa isang page ang listahan. --}}
@if ($paginator->count() > 0)
    @php
        $window  = 2;
        $current = $paginator->currentPage();
        $last    = $paginator->lastPage();
        $start   = max(1, $current - $window);
        $end     = min($last, $current + $window);
        $first   = $paginator->firstItem();
        $total   = $paginator->total();
    @endphp

    <div class="cmms-pag">
        <p class="cmms-pag__info">
            Showing
            @if ($first)
                <strong>{{ $first }}</strong> to <strong>{{ $paginator->lastItem() }}</strong>
            @else
                <strong>{{ $paginator->count() }}</strong>
            @endif
            of <strong>{{ $total }}</strong> results
        </p>

        @if ($paginator->hasPages())
        <nav class="cmms-pag__nav" aria-label="Pagination">
            @if ($paginator->onFirstPage())
                <span class="cmms-pag__btn cmms-pag__btn--disabled" aria-disabled="true">&lsaquo; Prev</span>
            @else
                <a class="cmms-pag__btn" href="{{ $paginator->previousPageUrl() }}" rel="prev">&lsaquo; Prev</a>
            @endif

            @if ($start > 1)
                <a class="cmms-pag__btn cmms-pag__num" href="{{ $paginator->url(1) }}" aria-label="Go to page 1">1</a>
                @if ($start > 2)
                    <span class="cmms-pag__gap" aria-hidden="true">&hellip;</span>
                @endif
            @endif

            @for ($i = $start; $i <= $end; $i++)
                @if ($i === $current)
                    <span class="cmms-pag__btn cmms-pag__num cmms-pag__btn--current" aria-current="page">{{ $i }}</span>
                @else
                    <a class="cmms-pag__btn cmms-pag__num" href="{{ $paginator->url($i) }}" aria-label="Go to page {{ $i }}">{{ $i }}</a>
                @endif
            @endfor

            @if ($end < $last)
                @if ($end < $last - 1)
                    <span class="cmms-pag__gap" aria-hidden="true">&hellip;</span>
                @endif
                <a class="cmms-pag__btn cmms-pag__num" href="{{ $paginator->url($last) }}" aria-label="Go to page {{ $last }}">{{ $last }}</a>
            @endif

            @if ($paginator->hasMorePages())
                <a class="cmms-pag__btn" href="{{ $paginator->nextPageUrl() }}" rel="next">Next &rsaquo;</a>
            @else
                <span class="cmms-pag__btn cmms-pag__btn--disabled" aria-disabled="true">Next &rsaquo;</span>
            @endif
        </nav>
        @endif
    </div>
@endif