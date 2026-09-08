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
