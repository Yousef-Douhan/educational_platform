@if ($paginator->hasPages())
    <nav class="crud-pagination" role="navigation">
        <div class="crud-pagination__pages">
            @if ($paginator->onFirstPage())
                <span class="page-link is-disabled" aria-disabled="true"><i class="fa-solid fa-angle-left"></i></span>
            @else
                <a class="page-link" href="{{ $paginator->previousPageUrl() }}" aria-label="{{ __('user-profiles.pagination.previous') }}"><i class="fa-solid fa-angle-left"></i></a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="page-dots">{{ $element }}</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="page-link is-active" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="page-link" href="{{ $paginator->nextPageUrl() }}" aria-label="{{ __('user-profiles.pagination.next') }}"><i class="fa-solid fa-angle-right"></i></a>
            @else
                <span class="page-link is-disabled" aria-disabled="true"><i class="fa-solid fa-angle-right"></i></span>
            @endif
        </div>
    </nav>
@endif