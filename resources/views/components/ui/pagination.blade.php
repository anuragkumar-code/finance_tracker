{{--
    Pagination in the app's own design language.

    Registered as the default paginator view in AppServiceProvider, so no page
    has to think about it.

    Numbered pages matter here in a way they do not on a blog: someone scanning
    back through a ledger knows roughly how far they need to go, and prev/next
    alone turns that into twenty clicks. First and last jumps are there for the
    same reason. Page controls still stay quiet — outline buttons, no fill —
    because they should never compete with the figures above them.
--}}
@if ($paginator->hasPages())
    @php
        $link = 'inline-flex h-8 min-w-8 items-center justify-center gap-1 rounded-md border '
            .'border-border bg-card px-2 text-[0.8125rem] shadow-xs transition-colors hover:bg-muted';
        $disabled = 'inline-flex h-8 min-w-8 items-center justify-center gap-1 rounded-md border '
            .'border-border px-2 text-[0.8125rem] text-subtle opacity-60';
    @endphp

    <nav role="navigation" aria-label="Pagination"
         class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

        <p class="text-xs text-muted-foreground">
            Showing <span class="font-medium text-foreground tabular">{{ $paginator->firstItem() }}</span>–<span
                class="font-medium text-foreground tabular">{{ $paginator->lastItem() }}</span>
            of <span class="font-medium text-foreground tabular">{{ number_format($paginator->total()) }}</span>
        </p>

        <div class="flex items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="{{ $disabled }} hidden sm:inline-flex" aria-disabled="true">
                    <x-ui.icon name="chevron-left" class="size-3.5 -mr-2" />
                    <x-ui.icon name="chevron-left" class="size-3.5" />
                </span>
                <span class="{{ $disabled }}" aria-disabled="true">
                    <x-ui.icon name="chevron-left" class="size-3.5" />
                    <span class="hidden sm:inline">Prev</span>
                </span>
            @else
                <a href="{{ $paginator->url(1) }}" class="{{ $link }} hidden sm:inline-flex" aria-label="First page">
                    <x-ui.icon name="chevron-left" class="size-3.5 -mr-2" />
                    <x-ui.icon name="chevron-left" class="size-3.5" />
                </a>
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $link }}">
                    <x-ui.icon name="chevron-left" class="size-3.5" />
                    <span class="hidden sm:inline">Prev</span>
                </a>
            @endif

            {{-- Numbers are hidden on narrow screens, where they would wrap
                 into a second row and be too small to hit accurately. --}}
            <div class="hidden items-center gap-1 md:flex">
                @foreach ($elements ?? [] as $element)
                    @if (is_string($element))
                        <span class="px-1 text-sm text-subtle">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page"
                                      class="inline-flex h-8 min-w-8 items-center justify-center rounded-md
                                             bg-primary px-2 text-[0.8125rem] font-medium
                                             text-primary-foreground shadow-xs tabular">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="{{ $link }} tabular"
                                   aria-label="Page {{ $page }}">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </div>

            <span class="px-1.5 text-xs text-muted-foreground tabular md:hidden">
                {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}
            </span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $link }}">
                    <span class="hidden sm:inline">Next</span>
                    <x-ui.icon name="chevron-right" class="size-3.5" />
                </a>
                <a href="{{ $paginator->url($paginator->lastPage()) }}" class="{{ $link }} hidden sm:inline-flex"
                   aria-label="Last page">
                    <x-ui.icon name="chevron-right" class="size-3.5 -mr-2" />
                    <x-ui.icon name="chevron-right" class="size-3.5" />
                </a>
            @else
                <span class="{{ $disabled }}" aria-disabled="true">
                    <span class="hidden sm:inline">Next</span>
                    <x-ui.icon name="chevron-right" class="size-3.5" />
                </span>
                <span class="{{ $disabled }} hidden sm:inline-flex" aria-disabled="true">
                    <x-ui.icon name="chevron-right" class="size-3.5 -mr-2" />
                    <x-ui.icon name="chevron-right" class="size-3.5" />
                </span>
            @endif
        </div>
    </nav>
@endif
