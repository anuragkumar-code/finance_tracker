<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <style>
        :root {
            --ft-bg: #f6f7f9;
            --ft-sidebar: #1c2434;
            --ft-sidebar-muted: #93a0b5;
            --ft-border: #e3e6ec;
            --ft-positive: #157347;
            --ft-negative: #b02a37;
        }

        body {
            background: var(--ft-bg);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 0.9375rem;
        }

        /* Money must line up column-to-column for scanning. */
        .money {
            font-variant-numeric: tabular-nums;
            font-feature-settings: "tnum";
            white-space: nowrap;
        }
        .money-pos { color: var(--ft-positive); }
        .money-neg { color: var(--ft-negative); }

        .sidebar {
            background: var(--ft-sidebar);
            min-height: 100vh;
            width: 232px;
            flex-shrink: 0;
        }
        .sidebar .navbar-brand {
            color: #fff;
            font-weight: 600;
            letter-spacing: -0.01em;
        }
        .sidebar .nav-link {
            color: var(--ft-sidebar-muted);
            border-radius: .375rem;
            padding: .5rem .75rem;
            margin-bottom: .125rem;
            font-size: .9rem;
        }
        .sidebar .nav-link:hover { color: #fff; background: rgba(255,255,255,.06); }
        .sidebar .nav-link.active { color: #fff; background: rgba(255,255,255,.12); font-weight: 500; }
        .sidebar .nav-section {
            color: #5f6b80;
            font-size: .7rem;
            text-transform: uppercase;
            letter-spacing: .06em;
            font-weight: 600;
            padding: .75rem .75rem .25rem;
        }
        .sidebar .nav-link.disabled {
            color: #4a5468;
            pointer-events: none;
        }

        .card {
            border: 1px solid var(--ft-border);
            border-radius: .5rem;
            box-shadow: 0 1px 2px rgba(16,24,40,.04);
        }
        .card-header {
            background: #fff;
            border-bottom: 1px solid var(--ft-border);
            font-weight: 600;
            font-size: .9rem;
        }

        .stat-label {
            font-size: .75rem;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #6b7385;
            font-weight: 600;
        }
        .stat-value {
            font-size: 1.5rem;
            font-weight: 600;
            letter-spacing: -0.02em;
        }

        .table > :not(caption) > * > * { padding: .6rem .75rem; }
        .table thead th {
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #6b7385;
            font-weight: 600;
            border-bottom-width: 1px;
        }

        /* Quick entry: big amount field, chip-style pickers. */
        .amount-input {
            font-size: 2.25rem;
            font-weight: 600;
            border: none;
            border-bottom: 2px solid var(--ft-border);
            border-radius: 0;
            padding: .25rem .5rem;
            font-variant-numeric: tabular-nums;
        }
        .amount-input:focus {
            box-shadow: none;
            border-bottom-color: #0d6efd;
        }
        .chip-group { display: flex; flex-wrap: wrap; gap: .375rem; }
        .chip input { position: absolute; opacity: 0; width: 0; height: 0; }
        .chip span {
            display: inline-block;
            padding: .35rem .75rem;
            border: 1px solid var(--ft-border);
            border-radius: 999px;
            background: #fff;
            cursor: pointer;
            font-size: .875rem;
            user-select: none;
            transition: all .12s ease;
        }
        .chip input:checked + span {
            background: #0d6efd;
            border-color: #0d6efd;
            color: #fff;
        }
        .chip input:focus-visible + span { outline: 2px solid #0d6efd; outline-offset: 2px; }

        .empty-state { text-align: center; padding: 2.5rem 1rem; color: #6b7385; }

        @media (max-width: 991.98px) {
            .sidebar { min-height: auto; width: 100%; }
        }
    </style>
    @stack('head')
</head>
<body>
<div class="d-lg-flex">

    <aside class="sidebar d-flex flex-column p-3">
        <a class="navbar-brand d-flex align-items-center mb-3 px-2" href="{{ route('dashboard') }}">
            <span>{{ config('app.name') }}</span>
        </a>

        <button class="btn btn-sm btn-outline-light d-lg-none mb-3" type="button"
                data-bs-toggle="collapse" data-bs-target="#sidebarNav">
            Menu
        </button>

        <nav class="collapse d-lg-block flex-grow-1" id="sidebarNav">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"
                       href="{{ route('dashboard') }}">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('quick-entry') ? 'active' : '' }}"
                       href="{{ route('quick-entry') }}">Quick Entry</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('transactions.*') ? 'active' : '' }}"
                       href="{{ route('transactions.index') }}">Transactions</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('accounts.*') ? 'active' : '' }}"
                       href="{{ route('accounts.index') }}">Accounts</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('credit-cards.*') ? 'active' : '' }}"
                       href="{{ route('credit-cards.index') }}">Credit Cards</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('loans.*') ? 'active' : '' }}"
                       href="{{ route('loans.index') }}">Loans</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('recurring.*') ? 'active' : '' }}"
                       href="{{ route('recurring.index') }}">Recurring</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('upcoming.*') ? 'active' : '' }}"
                       href="{{ route('upcoming.index') }}">Upcoming</a>
                </li>

                <li class="nav-section">Coming next</li>
                <li class="nav-item"><span class="nav-link disabled">Reports</span></li>

                <li class="nav-section">Settings</li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('settings.categories.*') ? 'active' : '' }}"
                       href="{{ route('settings.categories.index') }}">Categories</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('settings.people.*') ? 'active' : '' }}"
                       href="{{ route('settings.people.index') }}">People</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('settings.merchants.*') ? 'active' : '' }}"
                       href="{{ route('settings.merchants.index') }}">Merchants</a>
                </li>
            </ul>
        </nav>
    </aside>

    <main class="flex-grow-1 p-3 p-lg-4" style="min-width:0;">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h1 class="h4 mb-0">@yield('heading', 'Dashboard')</h1>
                @hasSection('subheading')
                    <div class="text-body-secondary small">@yield('subheading')</div>
                @endif
            </div>
            <div>@yield('actions')</div>
        </div>

        @if (session('status'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('status') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if (session('warning'))
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                {{ session('warning') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <div class="fw-semibold mb-1">Please check the following:</div>
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>
</div>

<script src="{{ asset('vendor/jquery/jquery.min.js') }}"></script>
<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('vendor/chartjs/chart.umd.min.js') }}"></script>
<script>
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });
</script>
@stack('scripts')
</body>
</html>
