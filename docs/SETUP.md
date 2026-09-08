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

It is still counted in **net worth**, because you do own it. Leaving it out there
would understate your real position as badly as ignoring your loans would
overstate it. The Accounts and Net worth screens show it as a separate
"set aside" line so the distinction stays visible.

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
