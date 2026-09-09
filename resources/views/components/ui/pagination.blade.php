{{--
    Pagination in the app's own design language.

    Registered as the default paginator view in AppServiceProvider, so no page
    has to think about it. Deliberately plain: page controls should be the
    quietest thing on a screen full of figures.
--}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" class="flex items-center justify-between gap-3">
        <p class="text-xs text-muted-foreground">
            Showing <span class="font-medium text-foreground tabular">{{ $paginator->firstItem() }}</span>–<span
                class="font-medium text-foreground tabular">{{ $paginator->lastItem() }}</span>
            of <span class="font-medium text-foreground tabular">{{ $paginator->total() }}</span>
        </p>

        <div class="flex items-center gap-1.5">
            @if ($paginator->onFirstPage())
                <span class="inline-flex h-8 items-center gap-1 rounded-md border border-border px-2.5
                             text-sm text-muted-foreground opacity-50" aria-disabled="true">
                    <x-ui.icon name="chevron-left" class="size-3.5" />
                    Previous
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
                   class="inline-flex h-8 items-center gap-1 rounded-md border border-border bg-card px-2.5
                          text-sm transition-colors hover:bg-muted">
                    <x-ui.icon name="chevron-left" class="size-3.5" />
                    Previous
                </a>
            @endif

            <span class="px-1 text-xs text-muted-foreground tabular">
                {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}
            </span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next"
                   class="inline-flex h-8 items-center gap-1 rounded-md border border-border bg-card px-2.5
                          text-sm transition-colors hover:bg-muted">
                    Next
                    <x-ui.icon name="chevron-right" class="size-3.5" />
                </a>
            @else
                <span class="inline-flex h-8 items-center gap-1 rounded-md border border-border px-2.5
                             text-sm text-muted-foreground opacity-50" aria-disabled="true">
                    Next
                    <x-ui.icon name="chevron-right" class="size-3.5" />
                </span>
            @endif
        </div>
    </nav>
@endif
