@if ($paginator->hasPages())
    <nav class="mt-8 flex items-center justify-center gap-3" aria-label="{{ __('Pages') }}">
        @if ($paginator->onFirstPage())
            <span class="btn-ghost opacity-40" aria-disabled="true">{{ __('Newer') }}</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="btn-ghost" rel="prev">{{ __('Newer') }}</a>
        @endif
        <span class="text-sm text-tuwaiq-lilac">{{ __('Page :current of :last', ['current' => $paginator->currentPage(), 'last' => $paginator->lastPage()]) }}</span>
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="btn-ghost" rel="next">{{ __('Older') }}</a>
        @else
            <span class="btn-ghost opacity-40" aria-disabled="true">{{ __('Older') }}</span>
        @endif
    </nav>
@endif
