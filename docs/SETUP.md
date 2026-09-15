# Setup & Local Operation

The app is private and runs on your own machine. Nothing is sent to any external
service: Bootstrap, jQuery and Chart.js are vendored into `public/vendor/`, so
the app works with the network disconnected.

## Requirements

- XAMPP with Apache + MariaDB/MySQL running
- PHP 8.2+ with `bcmath` and `pdo_mysql` enabled

## First run

```bash
# 1. Create the databases (once)
"C:/xampp/mysql/bin/mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS finance_tracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
"C:/xampp/mysql/bin/mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS finance_tracker_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Install dependencies
composer install

# 3. Build the schema and load starting categories/people
php artisan migrate
php artisan db:seed
```

Open **http://localhost/finance-tracker/public/**

There is no login — the app assumes whoever reaches it is the household
(spec section 32). Do not expose this directory to the internet.

## Getting started with real data

1. **Accounts → Add account.** Enter each bank account, cash and credit card
   with the balance it holds *today* and the date that balance is true as of.
   For a credit card, enter what you owe as a positive number.
2. **Quick Entry.** Record spending as it happens. Only amount and account are
   required; everything else is optional and gets easier as merchant defaults
   build up.
3. Spending on a card is recorded against the card. Paying the card bill later
   is a separate event that will *not* be counted as spending again.

## Everyday commands

```bash
php artisan test                            # verify the financial invariants still hold
php artisan finance:recalculate-balances    # re-derive every balance from the ledger
```

`finance:recalculate-balances` is a consistency check, not a repair tool you
should need. Balances are maintained on every write. If it ever reports drift,
something wrote to the `transactions` table outside the service layer — that is
worth investigating rather than shrugging off.

## Backups

The whole system is one MySQL database:

```bash
"C:/xampp/mysql/bin/mysqldump.exe" -u root finance_tracker > backup-$(date +%F).sql
```

Keep a copy somewhere other than this machine. The ledger is only as valuable as
its history.

## Testing

Tests run against `finance_tracker_test` (real MariaDB, not SQLite) because the
ledger relies on database-level CHECK constraints and DECIMAL semantics that
SQLite does not reproduce. The test database is wiped on every run; your real
data in `finance_tracker` is never touched.

## Credit cards (Phase 2)

Three separate things, deliberately never merged:

1. **A purchase** is recorded through Quick Entry with the card chosen as the
   account. It counts as spending on the day you buy, and raises what you owe.
   No bank account is touched.
2. **A statement** groups purchases you already recorded. Add it when your real
   statement arrives: the app pre-fills the total it grouped, and you replace it
   with the bank's figure. If the two differ, the gap is shown rather than
   quietly reconciled — it usually means a purchase was missed.
3. **A bill payment** moves money from your bank and reduces what you owe. It is
   never counted as spending again, because the purchases it settles were
   already counted when you made them.

Partial payments are supported; a statement tracks as awaiting payment, partly
paid, paid, or overdue.

## Loans and commitments (Phase 3)

**Loans** are described the simple way: monthly EMI, how many months, when it
started, and the day it is deducted. No interest rate is needed. The full
schedule is laid out from those four numbers, and the end date is worked out for
you.

Two things worth knowing:

- **"Still to pay" is cash, not principal.** It is the remaining EMIs multiplied
  by the EMI amount, so it includes future interest. It will not match the
  foreclosure figure your lender quotes.
- **A paid EMI counts as spending in full.** This household chose that over
  excluding it as debt repayment. The dashboard shows "incl. X loan EMIs" under
  the spending figure so both readings stay visible.

When adding a loan that started months ago, instalments already due are marked
paid but create no bank entries — those payments happened before you started
tracking, and inventing them would throw your balances off.

**Recurring commitments** (rent, bills, subscriptions, family support) are
forecasts, never assumptions. Each due date waits as "scheduled" until you
confirm it happened, at which point the real transaction is written. You can
confirm at a different amount when the actual bill differs, or skip a month.

**Upcoming** answers "how much can I actually spend": bank and cash, minus
everything committed in the next 7/30/60/90 days. Amounts from recurring
commitments are labelled *estimate*; EMIs and issued card bills are fixed.

## Set-aside money (emergency funds)

An account can be marked **Set aside** from Accounts → Edit. That account's money
is excluded from every "what can we spend" figure — available balance, the
dashboard, and "realistically available" — so an emergency fund never quietly
becomes part of the budget.

It is excluded from **everything**: available balance, net worth, assets, cash
flow, reports and charts. The one place it appears is its own "Set aside"
section at the bottom of the Accounts page, so you can still see the balance,
edit the account, and transfer money into it.

The trade-off, stated plainly: net worth now describes the money in play rather
than everything you own, and it is lower than your true position by the amount
of the fund. That is deliberate — the household asked for the fund to be out of
sight so it can never enter a spending decision.

Moving money into a set-aside account reads as money *leaving* in cash-flow
reports, rather than an internal transfer that nets to zero. It is still not
counted as spending.

## Assets

Things you own outside your accounts — land, a vehicle. **Value is optional**: an
asset with no value recorded still appears on the list and can be linked to the
loan that bought it, which is useful for seeing what the debt is against.

An unvalued asset contributes nothing to net worth. Since net worth counts every
rupee you owe, having unvalued assets makes the figure more pessimistic than
reality, and the Net worth screen says so rather than presenting a partial
picture as a complete one.

## Reports (Phase 4)

- **Reports** — one month, cut by category, subcategory, merchant, payer,
  beneficiary, account, purpose, and planned vs unplanned, plus a week-by-week
  breakdown. Every figure links into the transaction list filtered the same way,
  so any number can be opened and checked.
- **Trends** — 3/6/12 months side by side, with a category grid showing how
  spending shifts over time.
- **Net worth** — the balance sheet: accounts and assets against cards and loans.
- **Card report** — purchases and bill payments shown as separate columns, never
  added together.

## Reconciliation and export (Phase 5)

**Reconcile** compares what the app calculated against what your bank or card
actually shows. Pick an account, type in the real balance, and it tells you
whether the two agree.

When they do not, the app records the gap and **does not change its own
balance**. That is deliberate: a balance you can trace back to real entries is
worth more than one that always looks right. A gap almost always means an entry
was missed, so look for that first.

If you genuinely need to close a gap, "Adjust" posts a visible adjustment
transaction with your stated reason. It appears in the account history like any
other entry and is never counted as spending. The recorded difference stays
fixed at what it was on the day you checked, even as later entries arrive.

**Export** writes CSVs of your transactions and account balances. They stay on
this machine — nothing is sent anywhere. Voided entries are included and flagged
so the file reconciles against the app it came from, and set-aside accounts
appear in the account export even though they are hidden in the UI: a backup
that omitted them would not restore your real position.

## Quick commerce vs online shopping

Merchants carry a **channel** — how you buy from them, as opposed to what you
buy. A Blinkit order and a supermarket run are both "Food", but one is a
ten-minute habit that is easy to repeat without noticing, and it disappears
inside category totals otherwise.

Well-known names classify themselves as you type: Blinkit, Zepto and Instamart
become quick commerce; Amazon, Flipkart and Myntra become online shopping;
Swiggy and Zomato become food delivery. "Amazon Now" is read as quick commerce
even though plain "Amazon" is not. Anything unrecognised defaults to "in person"
and can be corrected under Settings → Merchants — a channel you set by hand is
never overwritten by the guesser.

The Reports page then shows a "How you bought it" section with the three online
figures broken out. The channel rows always add up to total spending, including
entries with no merchant recorded.

## Budgets (Phase 6)

Built last on purpose. Spec section 22 warns against imposing budgets before you
understand your own behaviour, so the app **will not suggest an amount** until it
has about three months of spending in a category. It says so plainly instead of
inventing a number. Setting a budget by hand is always allowed — you already know
what your rent is.

Budgets are **dated**. Raising the food budget in March does not rewrite how
February was judged: each month is measured against the target that was actually
in force at the time.

The comparison shows "% of budget used" next to "% of the month gone", because
70% spent on the 5th means something very different from the same figure on the
25th. A category running well ahead of the calendar is flagged as "spending
fast" before it goes over.

Categories with **no** budget still appear with their spending, so an unbudgeted
category quietly eating money cannot hide by never having had a target.

**Anomalies** compare a category against its own recent average rather than a
fixed threshold, so a household that always spends heavily on rent is not warned
about rent every month. Nothing is flagged until there is history to call it
unusual against.

Transfers, card bill payments and money moved to a set-aside account are not
spending and never count against a budget. Loan EMIs do, because this household
chose to count them as spending.

## The interface

The UI is Tailwind CSS + Alpine.js + Blade components, with Lucide icons inlined.
Bootstrap and jQuery have been removed entirely.

### Rebuilding the stylesheet

Tailwind compiles through its **standalone binary**, not Vite — Node on this
machine is 18 and Vite 7 requires 20+. Download it once:

```bash
mkdir -p tools
curl -L -o tools/tailwindcss.exe \
  https://github.com/tailwindlabs/tailwindcss/releases/latest/download/tailwindcss-windows-x64.exe
```

Then after editing any Blade template or `resources/css/app.css`:

```bash
./build-css.sh            # or ./build-css.sh --watch while working
```

The binary is gitignored (112MB); the compiled `public/build/app.css` is
committed, so a fresh clone runs with no build step at all.

### Design tokens

Colours live as CSS custom properties in `resources/css/app.css` and are exposed
to Tailwind as semantic names — `bg-card`, `text-muted-foreground`,
`border-border`, plus `text-income` / `text-expense` / `text-debt` for financial
meaning. Components never name a raw colour, so dark mode can be added later by
redefining the variables rather than editing every template.

### Typography

Inter Variable is self-hosted in `public/vendor/fonts` (two woff2 subsets,
122KB) rather than loaded from a font CDN, because the app must make no
third-party requests. The Latin subset is preloaded in the layout so text does
not reflow on first paint.

### Charts

`public/js/charts.js` owns the house chart style — palette from the `--chart-*`
tokens, no vertical gridlines, gradient area fills, rounded bars, and shared
INR formatting. Pages call `ftChart.area` / `.bars` / `.donut` / `.sparkline`
rather than constructing Chart.js config, so restyling every chart in the app
is one edit. Chart.js itself is vendored in `public/vendor/chartjs`.

### The shell

The sidebar collapses to an icon rail; the state is a `ft-rail` class on
`<html>`, restored by an inline script before first paint so the layout does
not jump on load. Ctrl/Cmd+K opens a command palette built from the same nav
array the sidebar renders, so a destination can never appear in one and not
the other.

### Components

Reusable Blade primitives live in `resources/views/components/ui` (button, card,
badge, input, select, textarea, checkbox, alert, dialog, dropdown, toast,
progress, stat, empty-state, pagination, icon, sort-header, filter-chips,
command-palette) and
`resources/views/components/finance` (money). Money is always rendered through
`<x-finance.money :amount="..." />` so Indian grouping and semantic colour stay
consistent everywhere.

Dialogs, dropdowns, the mobile sidebar, filters and toasts run on Alpine. Dialogs
trap focus, close on Escape and lock background scrolling.

### Tables, filters and sorting

Table styling lives in one place as the `.data-table` component class, so header
padding and row hover cannot drift between pages. Column sorting on the
transactions ledger is a link (`?sort=&dir=`), not JavaScript, so a sorted view
has its own URL and works with the back button; the sortable columns are matched
against a fixed list in `TransactionController::applySort`, and `id` always
breaks ties so rows cannot swap places between pages. Applied filters render as
removable chips through `<x-ui.filter-chips>`.

### Merchant groups

Merchants are filed into groups — Cabs & rides, E-commerce, Food delivery — which
is what makes the quick-entry picker a grouped, searchable list rather than one
long alphabetical run. The groups are seeded by
`2026_09_11_090000_create_merchant_groups_table` and curated under
Settings → Merchants.

A group is not a channel. The group says what kind of place it is; the channel
says how you buy from it, and only the channel drives the quick-commerce
figures. Blinkit sits in the E-commerce group and still counts as quick
commerce — which is the whole reason the two are separate columns. When a new
merchant is created, the name is consulted for a channel first and the group's
default only fills what the name left blank; reversing that order silently
reclassifies every quick-commerce merchant.

Filing existing merchants is a reviewable command rather than part of the
migration, because deciding that "Thar Retraunt" is a restaurant is a guess
about real data:

```bash
php artisan merchants:group             # dry run — prints the proposal, writes nothing
php artisan merchants:group --apply     # write it
php artisan merchants:group --regroup --apply   # also re-file merchants already grouped
```

Names that match no rule are left ungrouped rather than swept into "Other": an
empty cell asks to be filled in, a wrong label does not.

### Trips, events and shared costs

A trip or event (`events`) groups spends across categories so its whole cost is
one figure. Trip spends default to the **Holiday** category, which exists so
holiday meals do not count against the everyday Food budget.

Friends are `people` with `is_external = true`. They never appear in who-paid /
who-for pickers. Each friend's balance is an ordinary asset account
(`accounts.person_id`): positive when they owe the household, negative when the
household owes them. Every listing of "your accounts" excludes these — through
`Account::counted()` or `Account::own()` — and so do net worth and cash flow.

Settling up (`SharedExpenseService`) writes real ledger entries rather than
adjusting totals:

- **They owe** — the friend's share is taken out of the event's expense rows in
  proportion to their size (to the paisa), and moved to the friend's balance by
  a transfer from the same account on the same date. Every bank and card
  balance is identical before and after, on every date.
- **We owe** — the household's share of what the friend paid is recorded as an
  expense against the friend's balance.

Each reduction is stored as an allocation, so undoing a settlement adds it back
exactly, in any order. While a settlement stands, the entries it touched cannot
have their amount, account or date changed, or be voided — undo the settlement
first. Repayments are transfers (never income); a write-off is an expense.

Transfers are now sign-aware on liability accounts: a transfer *out of* a credit
card increases what is owed on it. Before this, transfers were only ever between
asset accounts and the rule ignored the account's side.

### Blade: never mix inline and block `@php`

Blade pairs an inline `@php($x = …)` with the next `@endphp` in the same file
and treats everything between as raw PHP, leaving `{{ }}`, loops and component
tags uncompiled. It shows up either as "unexpected end of file" at the last line
or as a page that renders scrambled with no error. Once a template has a block
`@php … @endphp`, use block form throughout it.
