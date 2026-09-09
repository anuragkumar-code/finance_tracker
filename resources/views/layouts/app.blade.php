<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ config('app.name') }}</title>

    {{-- Compiled by the Tailwind standalone CLI (see docs/SETUP.md); there is no
         Vite build here, so the stylesheet is committed. --}}
    <link rel="stylesheet" href="{{ asset('build/app.css') }}?v={{ filemtime(public_path('build/app.css')) }}">

    <script defer src="{{ asset('vendor/alpine/focus.min.js') }}"></script>
    <script defer src="{{ asset('vendor/alpine/alpine.min.js') }}"></script>
    @stack('head')
</head>
<body class="h-full bg-background text-foreground antialiased">

@php
    $nav = [
        'Main' => [
            ['route' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'layout-dashboard', 'match' => 'dashboard'],
            ['route' => 'quick-entry', 'label' => 'Quick Entry', 'icon' => 'zap', 'match' => 'quick-entry'],
            ['route' => 'transactions.index', 'label' => 'Transactions', 'icon' => 'arrow-left-right', 'match' => 'transactions.*'],
            ['route' => 'accounts.index', 'label' => 'Accounts', 'icon' => 'wallet', 'match' => 'accounts.*'],
            ['route' => 'credit-cards.index', 'label' => 'Credit Cards', 'icon' => 'credit-card', 'match' => 'credit-cards.*'],
            ['route' => 'loans.index', 'label' => 'Loans', 'icon' => 'landmark', 'match' => 'loans.*'],
            ['route' => 'recurring.index', 'label' => 'Recurring', 'icon' => 'repeat', 'match' => 'recurring.*'],
            ['route' => 'assets.index', 'label' => 'Assets', 'icon' => 'building-2', 'match' => 'assets.*'],
            ['route' => 'upcoming.index', 'label' => 'Upcoming', 'icon' => 'calendar-clock', 'match' => 'upcoming.*'],
        ],
        'Analytics' => [
            ['route' => 'reports.index', 'label' => 'Reports', 'icon' => 'chart-line', 'match' => 'reports.*'],
            ['route' => 'budgets.index', 'label' => 'Budgets', 'icon' => 'chart-pie', 'match' => 'budgets.*'],
            ['route' => 'reconciliations.index', 'label' => 'Reconcile', 'icon' => 'scale', 'match' => 'reconciliations.*'],
        ],
        'Settings' => [
            ['route' => 'settings.categories.index', 'label' => 'Categories', 'icon' => 'tags', 'match' => 'settings.categories.*'],
            ['route' => 'settings.people.index', 'label' => 'People', 'icon' => 'users', 'match' => 'settings.people.*'],
            ['route' => 'settings.merchants.index', 'label' => 'Merchants', 'icon' => 'store', 'match' => 'settings.merchants.*'],
        ],
    ];
@endphp

<div x-data="{ sidebar: false }" class="min-h-full">

    {{-- Mobile scrim. On small screens the sidebar becomes a drawer rather than
         collapsing inline, which kept pushing content down awkwardly. --}}
    <div x-show="sidebar" x-cloak x-transition.opacity
         x-on:click="sidebar = false"
         class="fixed inset-0 z-30 bg-[oklch(0.21_0.02_258_/_0.5)] lg:hidden"
         aria-hidden="true"></div>

    <aside x-cloak
           :class="sidebar ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
           class="fixed inset-y-0 left-0 z-40 flex w-[15.5rem] flex-col border-r
                  bg-[var(--sidebar)] transition-transform duration-200 lg:translate-x-0"
           style="border-color: var(--sidebar-border)">

        <div class="flex h-14 shrink-0 items-center gap-2 px-4"
             style="border-bottom: 1px solid var(--sidebar-border)">
            <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-2">
                <span class="flex size-7 shrink-0 items-center justify-center rounded-md bg-[var(--sidebar-accent)]">
                    <x-ui.icon name="piggy-bank" class="size-4" style="color: var(--sidebar-foreground)" />
                </span>
                <span class="truncate text-sm font-semibold" style="color: oklch(0.97 0.005 250)">
                    {{ config('app.name') }}
                </span>
            </a>
            <button type="button" x-on:click="sidebar = false"
                    class="ml-auto rounded-md p-1 lg:hidden" style="color: var(--sidebar-muted)">
                <x-ui.icon name="x" class="size-4" />
                <span class="sr-only">Close menu</span>
            </button>
        </div>

        <nav class="flex-1 space-y-5 overflow-y-auto px-3 py-4" aria-label="Main">
            @foreach ($nav as $section => $items)
                <div>
                    <p class="px-2.5 pb-1.5 text-[0.6875rem] font-semibold uppercase tracking-wider"
                       style="color: var(--sidebar-muted)">{{ $section }}</p>
                    <ul class="space-y-0.5">
                        @foreach ($items as $item)
                            @php($active = request()->routeIs($item['match']))
                            <li>
                                <a href="{{ route($item['route']) }}"
                                   @if ($active) aria-current="page" @endif
                                   class="group flex items-center gap-2.5 rounded-md px-2.5 py-[0.4375rem] text-sm transition-colors"
                                   style="{{ $active
                                       ? 'background-color: var(--sidebar-accent); color: oklch(0.98 0.003 250); font-weight: 500;'
                                       : 'color: var(--sidebar-foreground);' }}"
                                   @if (! $active)
                                       onmouseover="this.style.backgroundColor='var(--sidebar-accent)';this.style.color='oklch(0.98 0.003 250)'"
                                       onmouseout="this.style.backgroundColor='';this.style.color='var(--sidebar-foreground)'"
                                   @endif>
                                    <x-ui.icon :name="$item['icon']" class="size-4 shrink-0 opacity-80" />
                                    <span class="truncate">{{ $item['label'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>

        <div class="shrink-0 px-4 py-3 text-[0.6875rem]"
             style="border-top: 1px solid var(--sidebar-border); color: var(--sidebar-muted)">
            <span class="inline-flex items-center gap-1.5">
                <x-ui.icon name="lock" class="size-3" />
                Private · runs on this machine
            </span>
        </div>
    </aside>

    <div class="lg:pl-[15.5rem]">
        {{-- Mobile top bar. Desktop gets its page title from the header block
             instead, so the chrome stays out of the way. --}}
        <header class="sticky top-0 z-20 flex h-14 items-center gap-3 border-b border-border
                       bg-background/85 px-4 backdrop-blur lg:hidden">
            <button type="button" x-on:click="sidebar = true"
                    class="rounded-md p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground">
                <x-ui.icon name="menu" class="size-5" />
                <span class="sr-only">Open menu</span>
            </button>
            <span class="truncate text-sm font-semibold">@yield('heading', 'Dashboard')</span>
            <a href="{{ route('quick-entry') }}"
               class="ml-auto inline-flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground">
                <x-ui.icon name="plus" class="size-4" />
                <span class="sr-only">Quick entry</span>
            </a>
        </header>

        <main class="mx-auto w-full max-w-[85rem] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <h1 class="text-2xl font-semibold tracking-tight text-foreground">
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

<x-ui.toast />

<script src="{{ asset('vendor/chartjs/chart.umd.min.js') }}"></script>
<script>
    // Chart defaults live here so every chart in the app shares one visual
    // language rather than each page restating fonts and grid colours.
    if (window.Chart) {
        Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
        Chart.defaults.font.size = 11;
        Chart.defaults.color = 'oklch(0.554 0.02 257)';
        Chart.defaults.plugins.legend.display = false;
        Chart.defaults.plugins.tooltip.backgroundColor = 'oklch(0.21 0.02 258)';
        Chart.defaults.plugins.tooltip.padding = 10;
        Chart.defaults.plugins.tooltip.cornerRadius = 6;
        Chart.defaults.plugins.tooltip.displayColors = false;
        Chart.defaults.plugins.tooltip.titleFont = { weight: '600', size: 11 };
        Chart.defaults.maintainAspectRatio = false;

        window.ftChartPalette = [
            'oklch(0.55 0.13 253)', 'oklch(0.68 0.15 70)', 'oklch(0.58 0.16 27)',
            'oklch(0.62 0.10 190)', 'oklch(0.58 0.12 152)', 'oklch(0.62 0.13 310)',
            'oklch(0.66 0.10 40)', 'oklch(0.52 0.08 258)', 'oklch(0.70 0.09 120)',
            'oklch(0.60 0.06 250)',
        ];

        window.ftMoney = (v) => '₹' + Number(v).toLocaleString('en-IN', { maximumFractionDigits: 0 });
    }
</script>
@stack('scripts')
</body>
</html>
