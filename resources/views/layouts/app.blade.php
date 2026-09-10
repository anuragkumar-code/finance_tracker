<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ config('app.name') }}</title>

    {{-- Restore the collapsed rail before first paint. Doing this in Alpine
         instead would let the full-width sidebar render for a frame and then
         snap shut, which is the kind of flicker that makes an app feel cheap. --}}
    <script>
        try {
            if (localStorage.getItem('ft-rail') === '1') {
                document.documentElement.classList.add('ft-rail');
            }
        } catch (e) { /* private browsing — just render expanded */ }
    </script>

    {{-- Compiled by the Tailwind standalone CLI (see docs/SETUP.md); there is no
         Vite build here, so the stylesheet is committed. --}}
    <link rel="preload" as="font" type="font/woff2" href="{{ asset('vendor/fonts/inter-latin.woff2') }}" crossorigin>
    <link rel="stylesheet" href="{{ asset('build/app.css') }}?v={{ filemtime(public_path('build/app.css')) }}">

    <script defer src="{{ asset('vendor/alpine/focus.min.js') }}"></script>
    <script defer src="{{ asset('vendor/alpine/alpine.min.js') }}"></script>
    @stack('head')
</head>
<body class="h-full bg-background text-foreground antialiased">

@php
    $nav = [
        'Overview' => [
            ['route' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'layout-dashboard', 'match' => 'dashboard'],
            ['route' => 'quick-entry', 'label' => 'Quick Entry', 'icon' => 'zap', 'match' => 'quick-entry'],
            ['route' => 'transactions.index', 'label' => 'Transactions', 'icon' => 'arrow-left-right', 'match' => 'transactions.*'],
            ['route' => 'upcoming.index', 'label' => 'Upcoming', 'icon' => 'calendar-clock', 'match' => 'upcoming.*'],
        ],
        'Money' => [
            ['route' => 'accounts.index', 'label' => 'Accounts', 'icon' => 'wallet', 'match' => 'accounts.*'],
            ['route' => 'credit-cards.index', 'label' => 'Credit Cards', 'icon' => 'credit-card', 'match' => 'credit-cards.*'],
            ['route' => 'loans.index', 'label' => 'Loans', 'icon' => 'landmark', 'match' => 'loans.*'],
            ['route' => 'recurring.index', 'label' => 'Recurring', 'icon' => 'repeat', 'match' => 'recurring.*'],
            ['route' => 'assets.index', 'label' => 'Assets', 'icon' => 'building-2', 'match' => 'assets.*'],
        ],
        'Analytics' => [
            ['route' => 'reports.index', 'label' => 'Reports', 'icon' => 'chart-line', 'match' => 'reports.*'],
            ['route' => 'budgets.index', 'label' => 'Budgets', 'icon' => 'target', 'match' => 'budgets.*'],
            ['route' => 'reconciliations.index', 'label' => 'Reconcile', 'icon' => 'scale', 'match' => 'reconciliations.*'],
        ],
        'Settings' => [
            ['route' => 'settings.categories.index', 'label' => 'Categories', 'icon' => 'tags', 'match' => 'settings.categories.*'],
            ['route' => 'settings.people.index', 'label' => 'People', 'icon' => 'users', 'match' => 'settings.people.*'],
            ['route' => 'settings.merchants.index', 'label' => 'Merchants', 'icon' => 'store', 'match' => 'settings.merchants.*'],
        ],
    ];

    // The palette is fed from the same array the sidebar renders, so a new
    // destination can never be reachable by one and missing from the other.
    $paletteItems = collect($nav)
        ->flatMap(fn ($items, $section) => collect($items)->map(fn ($item) => [
            'label' => $item['label'],
            'group' => $section,
            'icon' => $item['icon'],
            'url' => route($item['route']),
        ]))
        ->concat([
            ['label' => 'Record a spend', 'group' => 'Actions', 'icon' => 'plus', 'url' => route('quick-entry')],
            ['label' => 'Record income', 'group' => 'Actions', 'icon' => 'trending-up', 'url' => route('transactions.create', ['type' => 'income'])],
            ['label' => 'Move money between accounts', 'group' => 'Actions', 'icon' => 'arrow-left-right', 'url' => route('transactions.create', ['type' => 'transfer'])],
            ['label' => 'Add an account', 'group' => 'Actions', 'icon' => 'wallet', 'url' => route('accounts.create')],
            ['label' => 'Trends over time', 'group' => 'Actions', 'icon' => 'chart-line', 'url' => route('reports.trends')],
            ['label' => 'Net worth', 'group' => 'Actions', 'icon' => 'scale', 'url' => route('reports.net-worth')],
            ['label' => 'Export transactions as CSV', 'group' => 'Actions', 'icon' => 'download', 'url' => route('export.transactions')],
        ])
        ->values();

    $currentSection = collect($nav)
        ->search(fn ($items) => collect($items)->contains(fn ($i) => request()->routeIs($i['match'])));
@endphp

<div x-data="{ sidebar: false }" class="min-h-full">

    {{-- Mobile scrim. On small screens the sidebar becomes a drawer rather than
         collapsing inline, which kept pushing content down awkwardly. --}}
    <div x-show="sidebar" x-cloak x-transition.opacity
         x-on:click="sidebar = false"
         class="fixed inset-0 z-30 bg-[oklch(0.21_0.02_258_/_0.4)] backdrop-blur-[1px] lg:hidden"
         aria-hidden="true"></div>

    <aside :class="sidebar ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
           class="nav-panel fixed inset-y-0 left-0 z-40 flex w-[15.5rem] flex-col border-r
                  border-sidebar-border bg-sidebar transition-transform duration-200
                  lg:w-[var(--nav-w)] lg:translate-x-0">

        <div class="flex h-14 shrink-0 items-center gap-2 px-3">
            <a href="{{ route('dashboard') }}"
               class="flex min-w-0 flex-1 items-center gap-2.5 rounded-lg px-1.5 py-1 hover:bg-sidebar-accent">
                <span class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-primary
                             text-primary-foreground shadow-xs">
                    <x-ui.icon name="piggy-bank" class="size-4" />
                </span>
                <span class="nav-label min-w-0 flex-1">
                    <span class="block truncate text-sm font-semibold tracking-tight">{{ config('app.name') }}</span>
                    <span class="block truncate text-[0.6875rem] text-sidebar-muted">Household ledger</span>
                </span>
            </a>
            <button type="button" x-on:click="sidebar = false"
                    class="rounded-md p-1 text-sidebar-muted hover:bg-sidebar-accent hover:text-foreground lg:hidden">
                <x-ui.icon name="x" class="size-4" />
                <span class="sr-only">Close menu</span>
            </button>
        </div>

        {{-- The palette trigger sits at the top of the rail as well as in the
             header, because that is where someone looks for "go somewhere". --}}
        <div class="px-3 pb-2">
            <button type="button" x-on:click="$dispatch('open-palette')"
                    class="nav-item w-full !justify-start border border-sidebar-border bg-card
                           text-subtle shadow-xs hover:text-foreground">
                <x-ui.icon name="search" class="size-4" />
                <span class="nav-label flex-1 text-left">Search…</span>
                <kbd class="nav-label rounded border border-border bg-muted px-1.5 py-px
                            text-[0.625rem] font-medium text-muted-foreground">Ctrl K</kbd>
            </button>
        </div>

        <nav class="scroll-thin flex-1 space-y-4 overflow-y-auto px-3 pb-4" aria-label="Main">
            @foreach ($nav as $section => $items)
                <div>
                    <p class="nav-section-label px-2 pb-1 text-[0.6875rem] font-semibold uppercase
                              tracking-wider text-sidebar-muted transition-opacity">{{ $section }}</p>
                    <ul class="space-y-px">
                        @foreach ($items as $item)
                            @php($active = request()->routeIs($item['match']))
                            <li>
                                <a href="{{ route($item['route']) }}" title="{{ $item['label'] }}"
                                   @if ($active) aria-current="page" @endif class="nav-item">
                                    <x-ui.icon :name="$item['icon']" class="size-[1.0625rem]" />
                                    <span class="nav-label truncate">{{ $item['label'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>

        <div class="shrink-0 border-t border-sidebar-border px-3 py-2.5">
            <span class="nav-item !cursor-default text-[0.6875rem] text-sidebar-muted hover:!bg-transparent">
                <x-ui.icon name="lock" class="size-3.5" />
                <span class="nav-label truncate">Private · runs on this machine</span>
            </span>
        </div>
    </aside>

    <div class="nav-offset lg:pl-[var(--nav-w)]">
        <header class="sticky top-0 z-20 flex h-14 items-center gap-2 border-b border-border
                       bg-background/80 px-4 backdrop-blur-md sm:px-6 lg:px-8">
            <button type="button" x-on:click="sidebar = true"
                    class="rounded-md p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground lg:hidden">
                <x-ui.icon name="menu" class="size-5" />
                <span class="sr-only">Open menu</span>
            </button>

            <button type="button" onclick="window.ftToggleRail()"
                    class="hidden rounded-md p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground lg:inline-flex">
                <x-ui.icon name="panel-left" class="size-[1.0625rem]" />
                <span class="sr-only">Collapse or expand the sidebar</span>
            </button>

            {{-- A trail rather than a single title: on a detail page it is the
                 quickest way back to the list you came from. --}}
            <nav aria-label="Breadcrumb" class="flex min-w-0 items-center gap-1.5 text-sm">
                @if ($currentSection)
                    <span class="hidden text-muted-foreground sm:inline">{{ $currentSection }}</span>
                    <x-ui.icon name="chevron-right" class="hidden size-3.5 text-subtle sm:inline" />
                @endif
                <span class="truncate font-medium">@yield('heading', 'Dashboard')</span>
            </nav>

            <div class="ml-auto flex shrink-0 items-center gap-2">
                <button type="button" x-on:click="$dispatch('open-palette')"
                        class="inline-flex h-8 items-center gap-1.5 rounded-md border border-border bg-card
                               px-2 text-sm text-subtle shadow-xs transition-colors hover:text-foreground lg:hidden">
                    <x-ui.icon name="search" class="size-4" />
                    <span class="sr-only">Search</span>
                </button>

                <a href="{{ route('quick-entry') }}"
                   class="inline-flex h-8 items-center gap-1.5 rounded-md bg-primary px-2.5 text-sm
                          font-medium text-primary-foreground shadow-xs transition-opacity hover:opacity-90">
                    <x-ui.icon name="plus" class="size-4" />
                    <span class="hidden sm:inline">Quick entry</span>
                    <span class="sr-only sm:hidden">Quick entry</span>
                </a>
            </div>
        </header>

        <main class="mx-auto w-full max-w-[88rem] px-4 py-5 sm:px-6 lg:px-8 lg:py-7">
            <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <h1 class="text-[1.375rem] font-semibold tracking-tight text-foreground sm:text-2xl">
                        @yield('heading', 'Dashboard')
                    </h1>
                    @hasSection('subheading')
                        <p class="mt-1 text-sm text-muted-foreground">@yield('subheading')</p>
                    @endif
                </div>
                @hasSection('actions')
                    <div class="flex shrink-0 flex-wrap items-center gap-2">@yield('actions')</div>
                @endif
            </div>

            @if ($errors->any())
                {{-- Field-level errors render against their inputs; this is the
                     summary for anything that could not be attached to one. --}}
                <div class="mb-5">
                    <x-ui.alert variant="destructive" title="Please check the following">
                        <ul class="mt-1 list-disc space-y-0.5 pl-4">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </x-ui.alert>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

<x-ui.command-palette :items="$paletteItems" />
<x-ui.toast />

<script>
    // Rail state is a class on <html> rather than component state so the header
    // toggle, the CSS geometry and the pre-paint restore all read one thing.
    window.ftToggleRail = function () {
        const collapsed = document.documentElement.classList.toggle('ft-rail');
        try { localStorage.setItem('ft-rail', collapsed ? '1' : '0'); } catch (e) {}
        // Charts size themselves to their container, which just changed width.
        window.dispatchEvent(new Event('resize'));
    };
</script>

<script src="{{ asset('vendor/chartjs/chart.umd.min.js') }}"></script>
<script src="{{ asset('js/charts.js') }}?v={{ filemtime(public_path('js/charts.js')) }}"></script>
@stack('scripts')
</body>
</html>
