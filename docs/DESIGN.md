# Personal Finance Management System — Core Design Document

**Scope:** This document fulfils Section 36 of `personal_finance_app_spec.md` — the seven design deliverables that must exist and be internally consistent before any UI work begins. Nothing here is code; it is the accounting model, schema, and architecture that all future migrations/controllers/views must conform to.

**Read against:** `personal_finance_app_spec.md` (all 40 sections), with primary source references cited inline (e.g. "§4", "§26").

---

## 0. Key Design Decisions (summary)

Before the detail, the load-bearing decisions this document makes, since §24 explicitly leaves the schema style open:

| # | Decision | Rationale |
|---|---|---|
| D1 | **Single `transactions` table, one row = one account-affecting "leg."** Multi-account events (Transfer, CC bill payment) are represented as **two linked rows** sharing a `transfer_group_id`. This is a lightweight ledger design, not a full double-entry chart of accounts. | Keeps Eloquent models simple (no separate `postings` table), while still making it structurally impossible to record a transfer as spending — a transfer's rows are never `type=expense`. |
| D2 | `transactions.amount` is **always stored unsigned** (the human-facing magnitude). A separate `balance_effect` enum (`increase`/`decrease`) says whether that leg adds to or subtracts from its account's balance. | Reporting queries (Total Spending, Credit-Card Spending, etc.) become `SUM(amount) WHERE type = ...` — no sign-flipping per account type needed in report SQL. Sign logic is isolated to `AccountBalanceService`. |
| D3 | **Account balances are computed from `opening_balance + Σ(transactions)`, cached in a denormalized column, and synchronously recalculated inside the same DB transaction on every create/update/delete/restore.** Not a background job (spec forbids Redis/queues as required infra), not purely on-the-fly (dashboard reads accounts constantly). | Satisfies Rule 8 (traceability: cache is always re-derivable from source rows) while keeping dashboard reads cheap. Household-scale data volume (thousands of rows/year) makes synchronous recompute trivial. |
| D4 | **Opening balances live only on `accounts.opening_balance` / `accounts.opening_balance_date` (and `loans.original_principal`/`outstanding_principal`). They are never rows in `transactions`.** | Automatically satisfies Rule 4 — opening balances cannot leak into `SUM(transactions.amount)`-based reports because they structurally aren't transaction rows. |
| D5 | **Loan EMI payments create exactly one `transactions` row** (the bank outflow, `type=liability_payment`) plus one `loan_payments` row holding the principal/interest split. The liability is reduced on `loans.outstanding_principal`, not via a second ledger leg against an `accounts` row. | Avoids inventing a fictitious "loan account" ledger leg; keeps the bank outflow single-counted while letting reports pull `interest_component` separately (§26 requires interest to count as spending, principal not to). |
| D6 | **Deletion is soft-delete only** (`deleted_at`), coupled with a required `void_reason`. Linked legs (same `transfer_group_id`) are always voided together inside one DB transaction. No hard delete is exposed in the UI for financially material rows. | Directly implements Rule 7. Because balances are computed by summing non-deleted rows, a void requires zero cascading updates elsewhere — Eloquent's default `SoftDeletes` scope handles it for free. |
| D7 | **Categories and Purpose/Planned-status are classification metadata on the transaction header, not ledger accounts.** Only `accounts` (bank/cash/credit card/investment) and `loans` carry real balances. | Keeps the ledger small and matches the spec's mental model — categories are for reporting, not for double-entry. |
| D8 | **`people` and `beneficiaries` are merged into one table** (`people`) with `can_be_payer` / `can_be_beneficiary` boolean flags. | §24 explicitly permits merging "if the implementation remains clear." The payer list (Me/Wife/Joint) is a strict subset of the beneficiary list (Me/Wife/Household/My Parents/Wife's Parents/Other); two near-duplicate tables would only add friction. Settings UI still shows two logical tabs, filtered by the flags. |
| D9 | **Asset Purchase transactions are excluded from "Total Spending."** | §26 doesn't explicitly list Asset Purchase in the include/exclude sets, but §12 says "avoid turning a major asset purchase into an ordinary lifestyle expense." This document formalizes that as: Asset Purchase moves money from one balance-sheet bucket (cash) to another (assets) — it is a transfer-like event, not consumption. Flagged explicitly here as an interpretation, not a literal spec quote. |

---

## 1. Database ERD / Relationship Design

### 1.1 Why a "linked-leg ledger" over "type + nullable columns"

§24 allows either a flat `transactions` table with a `type` enum and nullable specialized columns, **or** a ledger/linked-entry design, with the one hard constraint that double counting must be prevented.

A flat table with nullable `to_account_id`, `principal_component`, `card_statement_id`, etc. bolted onto one row works for *display* but breaks down for **balance calculation**, because a transfer's row would need to affect two accounts from one row, forcing every balance query to special-case `type=transfer` (`account_id` vs `to_account_id`) separately from every other type. That branching multiplies as more multi-account types are added (liability payment, asset purchase funded by loan, etc.) and is exactly the kind of place double-counting bugs creep in during edits.

The linked-leg design (D1/D2) instead guarantees: **every row in `transactions` affects exactly one account, in one direction, by one unsigned amount.** `Account::balance()` is always `opening_balance + Σ(increase legs) − Σ(decrease legs)` for that `account_id` alone — a single, uniform, un-branching query, regardless of transaction type. Multi-account events are just "2 rows with the same `transfer_group_id`," created and voided together by the service layer (§2, §7 below). This directly serves Rule 8 (traceability) and Rule 6 (editing consistency), and is still just one Eloquent model (`Transaction`) — no separate `postings`/journal table, no double-entry debit/credit vocabulary the household user will never see.

### 1.2 Full table list

Types are given as MySQL/Laravel-migration-style shorthand. All tables get `id BIGINT UNSIGNED AUTO_INCREMENT PK`, `created_at`, `updated_at` unless noted; soft-deletable tables also get `deleted_at NULL`.

#### `accounts`
| Column | Type | Null | Notes |
|---|---|---|---|
| name | VARCHAR(100) | NO | |
| type | ENUM('bank','cash','credit_card','investment','other_asset','other_liability') | NO | Loans are **not** an account type — see `loans` below. |
| institution | VARCHAR(100) | YES | |
| normal_balance | ENUM('asset','liability') | NO | Drives sign semantics in `AccountBalanceService`; set once at creation from `type`. |
| opening_balance | DECIMAL(14,2) | NO, default 0 | Signed: positive for assets held, and for liabilities (credit cards) represents amount *owed* as a positive magnitude — see §5. |
| opening_balance_date | DATE | NO | Everything on/after this date is "current era" ledger activity (§5). |
| cached_balance | DECIMAL(14,2) | NO, default 0 | Denormalized, recomputed synchronously (D3). |
| cached_balance_as_of | TIMESTAMP | YES | For staleness diagnostics/self-heal command. |
| currency | CHAR(3) | NO, default 'INR' | |
| is_active | BOOLEAN | NO, default true | |
| notes | TEXT | YES | |
| deleted_at | soft delete | | Accounts with history are never hard-deleted. |

Indexes: `(type)`, `(is_active)`.

#### `categories`
| Column | Type | Null | Notes |
|---|---|---|---|
| name | VARCHAR(100) | NO | |
| parent_id | BIGINT FK→categories.id | YES | |
| applies_to | ENUM('expense','income','both') | NO, default 'expense' | |
| is_active | BOOLEAN | NO, default true | |
| sort_order | INT | NO, default 0 | |

Indexes: `(parent_id)`, `(applies_to)`.

#### `people` (payer + beneficiary, merged per D8)
| Column | Type | Null | Notes |
|---|---|---|---|
| name | VARCHAR(100) | NO | |
| relationship | VARCHAR(50) | YES | e.g. "spouse", "my parents", "wife's parents" |
| is_household | BOOLEAN | NO, default false | for "Joint/Household" |
| can_be_payer | BOOLEAN | NO, default true | |
| can_be_beneficiary | BOOLEAN | NO, default true | |
| is_active | BOOLEAN | NO, default true | |
| sort_order | INT | NO, default 0 | |

#### `merchants`
| Column | Type | Null |
|---|---|---|
| name | VARCHAR(150) | NO, unique |
| default_category_id | BIGINT FK→categories.id | YES |
| default_account_id | BIGINT FK→accounts.id | YES |
| default_payer_id | BIGINT FK→people.id | YES |
| default_beneficiary_id | BIGINT FK→people.id | YES |
| is_active | BOOLEAN | NO, default true |

Index: `(name)` for autocomplete.

#### `tags` / `transaction_tag`
`tags(id, name VARCHAR(50) unique)`; `transaction_tag(transaction_id FK, tag_id FK, PK(transaction_id, tag_id))`.

#### `transactions` (the core ledger)
| Column | Type | Null | Notes |
|---|---|---|---|
| transaction_date | DATE | NO | Date-only per §9/§16 usage; if intraday ordering matters later, add `transaction_time`. |
| type | ENUM('expense','income','transfer','asset_purchase','liability_payment','adjustment') | NO | §9 type list. |
| leg_role | ENUM('single','transfer_from','transfer_to','payment_from','payment_to') | NO, default 'single' | Disambiguates which side of a linked pair this row is, for UI rendering ("HDFC Bank → ICICI Bank"). |
| transfer_group_id | CHAR(36) | YES | UUID shared by the 2 legs of a Transfer/Liability-Payment-to-CC. Indexed. |
| account_id | BIGINT FK→accounts.id | NO | The **one** account this row affects. |
| amount | DECIMAL(14,2) UNSIGNED | NO | Always positive magnitude (D2). |
| balance_effect | ENUM('increase','decrease') | NO | Set by the service layer at creation (see matrix §1.3), never user-edited directly. |
| category_id | BIGINT FK→categories.id | YES | |
| subcategory_id | BIGINT FK→categories.id | YES | Must be a child of `category_id` (app-level check). |
| payer_id | BIGINT FK→people.id | YES | |
| beneficiary_id | BIGINT FK→people.id | YES | |
| merchant_id | BIGINT FK→merchants.id | YES | |
| planned_status | ENUM('planned','unplanned','emergency') | YES | |
| purpose | ENUM('necessity','lifestyle','family','investment','debt','emergency','discretionary') | YES | |
| description | VARCHAR(255) | YES | |
| notes | TEXT | YES | |
| reference | VARCHAR(100) | YES | |
| credit_card_payment_id | BIGINT FK→credit_card_payments.id | YES | Set when `type=liability_payment` against a card. |
| loan_payment_id | BIGINT FK→loan_payments.id | YES | Set when `type=liability_payment` against a loan. |
| asset_id | BIGINT FK→assets.id | YES | Set when `type=asset_purchase`. |
| reconciliation_id | BIGINT FK→reconciliations.id | YES | Set when `type=adjustment`. |
| recurring_transaction_id | BIGINT FK→recurring_transactions.id | YES | Provenance if posted from a recurring occurrence. |
| source | ENUM('manual','recurring','import') | NO, default 'manual' | |
| void_reason | VARCHAR(255) | YES | Required by app logic when soft-deleting a posted transaction. |
| deleted_at | soft delete | | Rule 7. |

Indexes: `(account_id, transaction_date)`, `(type, transaction_date)`, `(transfer_group_id)`, `(category_id)`, `(payer_id)`, `(beneficiary_id)`, `(merchant_id)`.

#### `transaction_splits` (optional per-transaction category/beneficiary split, §37 "Split expense")
| Column | Type | Null |
|---|---|---|
| transaction_id | BIGINT FK→transactions.id | NO |
| category_id | BIGINT FK→categories.id | YES |
| subcategory_id | BIGINT FK→categories.id | YES |
| beneficiary_id | BIGINT FK→people.id | YES |
| amount | DECIMAL(14,2) | NO |
| notes | VARCHAR(255) | YES |

App-level invariant: `SUM(transaction_splits.amount)` for a `transaction_id` must equal `transactions.amount` when any split rows exist. The parent `transactions` row remains the single ledger leg that affects `account_id`'s balance — splits only refine category/beneficiary reporting, never the account effect (prevents double counting by construction).

#### `credit_cards`
| Column | Type | Null | Notes |
|---|---|---|---|
| account_id | BIGINT FK→accounts.id | NO, unique | 1:1 — every credit card *is* an `accounts` row of `type=credit_card`. |
| card_name | VARCHAR(100) | NO | |
| credit_limit | DECIMAL(14,2) | NO | |
| statement_day | TINYINT (1–31) | NO | |
| payment_due_day | TINYINT (1–31) | NO | |
| annual_fee | DECIMAL(10,2) | YES | |
| is_active | BOOLEAN | NO, default true | |
| notes | TEXT | YES | |

#### `credit_card_statements`
| Column | Type | Null | Notes |
|---|---|---|---|
| credit_card_id | BIGINT FK→credit_cards.id | NO | |
| period_start | DATE | NO | |
| period_end | DATE | NO | |
| statement_date | DATE | NO | |
| statement_amount | DECIMAL(14,2) | NO | Snapshot sum of linked purchases for this cycle. |
| carried_balance | DECIMAL(14,2) | NO, default 0 | Unpaid balance rolled forward from prior statement, if any. |
| due_date | DATE | NO | |
| minimum_due | DECIMAL(14,2) | YES | |
| status | ENUM('open','generated','partially_paid','paid','overdue') | NO, default 'open' | |
| notes | TEXT | YES | |

Unique: `(credit_card_id, period_start, period_end)`.

#### `credit_card_statement_items` (traceability pivot — never a new expense, §4)
| Column | Type | Null | Notes |
|---|---|---|---|
| statement_id | BIGINT FK→credit_card_statements.id | NO | |
| transaction_id | BIGINT FK→transactions.id | NO | |
| amount_snapshot | DECIMAL(14,2) | NO | Copy of `transactions.amount` at generation time, for stable statement history even if the source transaction is later edited (edits to already-`generated` statements should warn/require regeneration). |

Unique: `(statement_id, transaction_id)`.

#### `credit_card_payments`
| Column | Type | Null | Notes |
|---|---|---|---|
| credit_card_id | BIGINT FK→credit_cards.id | NO | |
| statement_id | BIGINT FK→credit_card_statements.id | YES | Nullable to allow advance/extra payments not tied to one statement. |
| source_account_id | BIGINT FK→accounts.id | NO | The bank/cash account paying the bill. |
| payment_date | DATE | NO | |
| amount | DECIMAL(14,2) | NO | |
| transfer_group_id | CHAR(36) | NO | Joins to the 2 `transactions` legs it created (indexed, not an FK — see §1.1). |
| notes | TEXT | YES | |

#### `loans`
| Column | Type | Null | Notes |
|---|---|---|---|
| name | VARCHAR(100) | NO | |
| lender | VARCHAR(100) | YES | |
| original_principal | DECIMAL(14,2) | NO | |
| outstanding_principal | DECIMAL(14,2) | NO | Cached; recomputed as `original_principal − Σ(loan_payments.principal_component)`. |
| interest_rate | DECIMAL(6,3) | YES | |
| emi_amount | DECIMAL(14,2) | YES | |
| start_date | DATE | NO | |
| end_date | DATE | YES | |
| due_day | TINYINT (1–31) | YES | |
| payment_account_id | BIGINT FK→accounts.id | YES | Default EMI payment source. |
| status | ENUM('active','closed','defaulted') | NO, default 'active' | |
| notes | TEXT | YES | |

#### `loan_payments`
| Column | Type | Null | Notes |
|---|---|---|---|
| loan_id | BIGINT FK→loans.id | NO | |
| payment_date | DATE | NO | |
| total_amount | DECIMAL(14,2) | NO | |
| principal_component | DECIMAL(14,2) | NO | |
| interest_component | DECIMAL(14,2) | NO | |
| other_charges | DECIMAL(14,2) | NO, default 0 | |
| account_id | BIGINT FK→accounts.id | NO | Payment source. |
| transaction_id | BIGINT FK→transactions.id | YES | The single bank-outflow ledger leg (D5). |
| notes | TEXT | YES | |

App-level invariant: `principal_component + interest_component + other_charges = total_amount`.

#### `recurring_transactions`
| Column | Type | Null |
|---|---|---|
| name | VARCHAR(150) | NO |
| type | ENUM('expense','income','transfer','liability_payment') | NO, default 'expense' |
| amount | DECIMAL(14,2) | NO |
| frequency | ENUM('daily','weekly','monthly','yearly','custom') | NO |
| interval_count | INT | NO, default 1 |
| next_due_date | DATE | NO |
| end_date | DATE | YES |
| account_id | BIGINT FK→accounts.id | NO |
| category_id | BIGINT FK→categories.id | YES |
| subcategory_id | BIGINT FK→categories.id | YES |
| payer_id | BIGINT FK→people.id | YES |
| beneficiary_id | BIGINT FK→people.id | YES |
| merchant_id | BIGINT FK→merchants.id | YES |
| purpose | ENUM(...) | YES |
| planned_status | ENUM(...) | NO, default 'planned' |
| is_active | BOOLEAN | NO, default true |
| notes | TEXT | YES |

#### `recurring_transaction_occurrences`
| Column | Type | Null | Notes |
|---|---|---|---|
| recurring_transaction_id | BIGINT FK→recurring_transactions.id | NO | |
| due_date | DATE | NO | |
| status | ENUM('pending','confirmed','skipped') | NO, default 'pending' | |
| transaction_id | BIGINT FK→transactions.id | YES | Set once confirmed and posted. |

Unique: `(recurring_transaction_id, due_date)`. This exists so §13's requirement — "generate upcoming expected entries rather than silently assuming payment was completed" — has a concrete state machine per due date, instead of mutating `next_due_date` in place and losing history.

#### `assets`
| Column | Type | Null |
|---|---|---|
| name | VARCHAR(150) | NO |
| type | VARCHAR(50) | NO |
| purchase_date | DATE | NO |
| purchase_value | DECIMAL(14,2) | NO |
| current_value | DECIMAL(14,2) | YES |
| linked_loan_id | BIGINT FK→loans.id | YES |
| purchase_transaction_id | BIGINT FK→transactions.id | YES |
| notes | TEXT | YES |

#### `reconciliations`
| Column | Type | Null | Notes |
|---|---|---|---|
| account_id | BIGINT FK→accounts.id | NO | |
| reconciliation_date | DATE | NO | |
| actual_balance | DECIMAL(14,2) | NO | |
| system_balance | DECIMAL(14,2) | NO | Snapshot of `AccountBalanceService::balance()` at the time of reconciliation. |
| difference | DECIMAL(14,2) | NO | `actual_balance − system_balance`, stored (not generated) so history is stable even if later transactions change system balance retroactively. |
| adjustment_transaction_id | BIGINT FK→transactions.id | YES | |
| note | TEXT | YES | |
| status | ENUM('reconciled','discrepancy','resolved') | NO | |

### 1.3 Balance-effect sign matrix (used by the service layer, not stored per-row beyond `balance_effect`)

| Type | Leg | Account normal_balance | balance_effect |
|---|---|---|---|
| Expense | single | asset (bank/cash/investment) | decrease |
| Expense | single | liability (credit card) | increase (owed goes up) |
| Income | single | asset | increase |
| Transfer | `transfer_from` | asset | decrease |
| Transfer | `transfer_to` | asset | increase |
| Liability Payment (credit card) | `payment_from` | asset | decrease |
| Liability Payment (credit card) | `payment_to` | liability (credit card) | decrease (owed goes down) |
| Liability Payment (loan) | single (bank leg only, D5) | asset | decrease |
| Asset Purchase | single | asset | decrease |
| Asset Purchase | single | liability (bought on card) | increase |
| Adjustment | single | asset or liability | increase or decrease, whichever closes the reconciliation gap |

This matrix is implemented once, in `AccountBalanceService`/`TransactionService`, and is never re-derived ad hoc in a controller or Blade view.

---

## 2. Transaction Lifecycle Design

### 2.1 Balance computation: on-the-fly source of truth + synchronous cache (D3)

`Account::balance(asOf: ?Date)` is defined as:

```
balance = opening_balance
        + Σ(amount WHERE account_id = X AND balance_effect = 'increase' AND transaction_date <= asOf AND deleted_at IS NULL)
        − Σ(amount WHERE account_id = X AND balance_effect = 'decrease' AND transaction_date <= asOf AND deleted_at IS NULL)
```

This is the **source of truth** and is what Rule 8 ("every balance should be traceable") requires — any displayed number must be explainable as opening balance + a filterable list of ledger rows, which is exactly this query (and its underlying row set is what "drill down" in §27 clicks into).

`accounts.cached_balance` denormalizes this for dashboard performance. It is recalculated by `AccountBalanceService::recalculate(Account $account)` called synchronously, inside the same DB transaction, whenever:
- a transaction affecting that account is created, updated, restored, or soft-deleted,
- a reconciliation adjustment is posted,
- (self-heal) an artisan command `php artisan finance:recalculate-balances` re-derives every account from scratch — run manually or via the daily scheduler, purely as a consistency check, never as the primary mechanism.

No Redis/queue is introduced (per §32); recompute is a single indexed `SUM()` query per affected account, cheap at household transaction volumes.

### 2.2 Create

All creation goes through a type-specific service method (never direct `Transaction::create()` from a controller — see §7). Each method:
1. Validates via a Form Request (amount > 0, required dimensions for that type, account is active, etc.).
2. Wraps in `DB::transaction()`.
3. Inserts 1 row (Expense/Income/Adjustment/Asset Purchase/Loan Payment's bank leg) or 2 linked rows sharing a new `transfer_group_id` (Transfer, Credit-Card Liability Payment).
4. Calls `AccountBalanceService::recalculate()` for every account touched.
5. For loan payments, also updates `loans.outstanding_principal` (recomputed as `original_principal − Σ(active loan_payments.principal_component)`, not decremented in place, to stay edit-safe — see 2.3).
6. For credit-card purchases, no extra step is needed beyond the single Expense leg — statements are generated later by grouping (see §3).

### 2.3 Edit (Rule 6)

- **Single-leg transactions** (Expense, Income, Adjustment, Asset Purchase, the loan-payment bank leg): editing `amount`/`account_id`/`transaction_date`/`balance_effect`-relevant fields directly updates the one row; because balances are always re-derived by summing live rows, no other table needs to change. This is the direct payoff of D3/D2: editing is "update one row, recalc the touched account(s)" with no cascading writes.
- **Linked pairs** (Transfer, Credit-Card Liability Payment): the service layer loads both legs by `transfer_group_id` and updates them together inside one DB transaction (e.g. changing a transfer's amount updates both the `transfer_from` and `transfer_to` rows to the same new amount so they never drift apart). Individual legs are never editable independently through the UI/API.
- **Loan payments**: editing a `loan_payments` row (e.g. correcting the principal/interest split) must also update the linked `transactions.amount` if `total_amount` changed, then trigger `loans.outstanding_principal` recomputation and `AccountBalanceService::recalculate()` for the payment account — all in one service method (`LoanService::updatePayment()`), never by editing the two tables separately from two different controllers.
- **Credit-card statements**: once `status != 'open'`, editing a transaction that belongs to a generated statement should warn the user and offer "regenerate statement" (idempotent: delete old `credit_card_statement_items`, re-sum, re-link) rather than silently letting `statement_amount` go stale.

### 2.4 Delete (Rule 7)

There is no hard delete of a posted `transactions`, `loan_payments`, `credit_card_payments`, or `reconciliations` row from the UI. "Delete" always means:
1. Require a `void_reason`.
2. `DB::transaction()`: soft-delete the row (and its sibling leg via `transfer_group_id`, if any) using Eloquent `SoftDeletes`.
3. Recalculate every affected account's `cached_balance`.
4. If the row was linked to a `loan_payments`/`credit_card_statement_items`/`assets` row, cascade-void or flag that linked row as needing attention (e.g. an asset whose `purchase_transaction_id` was voided should be flagged, not silently orphaned).

Because `SoftDeletes`' default global scope excludes `deleted_at IS NOT NULL` rows automatically, every balance/report query is correct with zero extra `WHERE` clauses to remember — this is the practical reason D3's "sum live rows" design was chosen over a cached-only model, where a delete would require someone to remember to also decrement a cached total.

### 2.5 Effect-per-type summary (matches §37 test list)

| Type | Account(s) affected | Total Spending effect |
|---|---|---|
| Expense (bank/cash) | that account: decrease | + amount |
| Expense (credit card) | card account: increase (owed) | + amount |
| Income | asset account: increase | (excluded from spending; counted in income reports) |
| Transfer | source: decrease, dest: increase | none |
| Liability Payment (CC) | bank: decrease, card: decrease (owed) | none |
| Liability Payment (loan) | bank: decrease; `loans.outstanding_principal`: decrease by principal_component | + interest_component only |
| Asset Purchase | paying account: decrease (or card: increase) | none (excluded, D9) |
| Adjustment | reconciled account: increase or decrease | none |

---

## 3. Credit-Card Accounting Flow

Mechanics, mapped to §4's requirements and traced through the schema.

### 3.1 Purchase-time recognition

**Worked example, part 1 — the ₹2,000 shopping purchase on 8 Sep, paid on HDFC Credit Card:**

`TransactionService::recordExpense()` inserts one `transactions` row:

```
type = expense
account_id = <HDFC Credit Card's accounts.id>   (normal_balance = liability)
amount = 2000.00
balance_effect = increase
category_id = <Shopping>
transaction_date = 2026-09-08
leg_role = single
```

`AccountBalanceService::recalculate()` updates the HDFC Credit Card account's `cached_balance` from its prior value to `prior + 2000` (its balance represents **amount owed**, a positive magnitude for a liability account). Bank balance is untouched — nothing was posted against any bank `accounts` row. This is exactly §37's assertion: "Expense increases, card liability increases, bank balance unchanged."

### 3.2 Statement generation (grouping, not creating)

On (or after) `statement_day`, a `CreditCardService::generateStatement($creditCard, $periodStart, $periodEnd)`:
1. Creates a `credit_card_statements` row (`period_start`, `period_end`, `statement_date`, `due_date` computed from `payment_due_day`).
2. Queries existing `transactions WHERE account_id = <card's account> AND transaction_date BETWEEN period_start AND period_end AND deleted_at IS NULL` (any `type`, though normally `expense`), sums their `amount` (increase legs) minus any `decrease` legs (e.g. a refund/adjustment) into `statement_amount`.
3. Inserts one `credit_card_statement_items` row per transaction (`amount_snapshot` = that row's amount) purely for traceability/drill-down — **it inserts zero new `transactions` rows.** This satisfies §4's "the statement itself is not a new expense."
4. `carried_balance` = any unpaid amount from the prior statement (outstanding not yet paid).

**Outstanding** (§4 required behavior) = the credit card account's live `cached_balance` (or on-the-fly `AccountBalanceService::balance()`), i.e. `opening_balance + Σ(increase legs, all-time) − Σ(decrease legs, all-time)` — not scoped to one statement, so it correctly reflects the *current* total owed even mid-cycle.

**Available credit** = `credit_cards.credit_limit − outstanding`.

### 3.3 Payment / reconciliation flow

**Worked example, part 2 — paying ₹25,000 of the card bill from HDFC Bank:**

`CreditCardService::pay($creditCard, $statement, $sourceAccount, $amount, $date)`:
1. Creates a `credit_card_payments` row (`credit_card_id`, `statement_id`, `source_account_id`, `amount=25000`, a new `transfer_group_id`).
2. Inserts **two** `transactions` rows sharing that `transfer_group_id`:
   ```
   Row A: type=liability_payment, leg_role=payment_from, account_id=<HDFC Bank>,
          amount=25000, balance_effect=decrease, credit_card_payment_id=<A's id>

   Row B: type=liability_payment, leg_role=payment_to, account_id=<HDFC Credit Card>,
          amount=25000, balance_effect=decrease, credit_card_payment_id=<same>
   ```
3. `AccountBalanceService::recalculate()` for both accounts: bank drops by 25,000, card's "owed" drops by 25,000.
4. Updates `credit_card_statements.status` (paid/partially_paid based on cumulative `credit_card_payments.amount` vs `statement_amount + carried_balance`).

No row here has `type=expense`; §4's requirement "the payment must not create another expense" and §37's "Expense total unchanged" are structurally guaranteed — `SUM(amount) WHERE type='expense'` for the period is untouched by this operation because neither leg is `type=expense`.

### 3.4 Reports that never double-count

Because Total Spending (§4/§26/§6 below) only ever sums `type='expense'` rows, and card payments are always `type='liability_payment'`, the two are mechanically incapable of overlapping in that metric. Credit-Card Spending (a separate §19F metric) sums the same `expense` rows filtered to card accounts, and Card Payment amount is reported separately from `credit_card_payments`/the `payment_from`/`payment_to` legs — satisfying §19F's "purchase volume and payment amount must be shown separately."

---

## 4. Loan/EMI Accounting Flow

Per §11, V1 uses **manual entry** of the principal/interest split (no auto-amortization schedule engine).

### 4.1 Recording an EMI payment

`LoanService::recordPayment($loan, $paymentDate, $totalAmount, $principalComponent, $interestComponent, $accountId, $otherCharges = 0)`:

1. Validates `principalComponent + interestComponent + otherCharges == totalAmount`.
2. `DB::transaction()`:
   - Inserts one `transactions` row: `type=liability_payment`, `account_id=<payment account>`, `amount=totalAmount`, `balance_effect=decrease`, `leg_role=single` (there is no second ledger leg against an "account" for the loan itself — see D5).
   - Inserts a `loan_payments` row with the split, `transaction_id` pointing at the row above.
   - Recomputes `loans.outstanding_principal = original_principal − Σ(loan_payments.principal_component WHERE deleted_at IS NULL)` — recomputed from the sum, not decremented in place, so edits/voids of a past `loan_payments` row are automatically correct without a separate reconciliation step.
   - `AccountBalanceService::recalculate()` for the payment account.

**Worked example — EMI ₹32,500 = ₹24,000 principal + ₹8,500 interest (§11):**
- Bank account: `cached_balance` drops by 32,500 (one ledger leg, matches §37's "Bank decreases by EMI").
- `loans.outstanding_principal` drops by 24,000.
- `interest_component=8,500` is *not* a `transactions` row at all — it's a column on `loan_payments`, picked up by the Total Spending query via UNION (§6) so that "interest contributes to expense reporting" (§11) without a second bank-outflow row existing (which would double the cash-out effect).

### 4.2 Loan report figures (§19I)

- Outstanding principal: `loans.outstanding_principal` (live).
- EMI: `loans.emi_amount`.
- Principal paid (period): `Σ(loan_payments.principal_component WHERE payment_date BETWEEN ...)`.
- Interest paid (period): `Σ(loan_payments.interest_component WHERE payment_date BETWEEN ...)`.
- Upcoming EMI: derived from `loans.due_day` + `emi_amount`, surfaced through the same Upcoming Obligations mechanism as recurring transactions (§6.6).
- Loan trend over time: `outstanding_principal` reconstructed per month as `original_principal − Σ(principal_component WHERE payment_date <= month_end)` (a running total, computed on read — cheap at this data volume, no need to persist monthly snapshots for V1).

---

## 5. Opening Balance and Reconciliation Strategy

### 5.1 Opening balances (§8)

Opening balances are **not** transaction rows (D4). Each `accounts` row stores `opening_balance` + `opening_balance_date`; each `loans` row stores `original_principal` (its de facto opening position) directly. Example from §8:

```
accounts:  HDFC Bank        opening_balance =   85,000  opening_balance_date = <adoption date>
           ICICI Bank       opening_balance = 1,10,000  opening_balance_date = <adoption date>
           Cash             opening_balance =   15,000  opening_balance_date = <adoption date>
           HDFC Credit Card opening_balance =   45,000  opening_balance_date = <adoption date>  (liability: owed)

loans:     Land Loan  original_principal = 12,50,000  outstanding_principal = 12,50,000
           Bike Loan  original_principal =  1,45,000  outstanding_principal =  1,45,000
```

Because every reporting/spending query filters on `transactions.type` and/or `transaction_date`, and opening balances never populate `transactions`, they cannot distort current-period spend totals (Rule 4) by construction — there is no flag to forget to check.

The distinction §8 asks for — Opening balance / Historical transaction / Current-period transaction / Adjustment — maps directly to: `accounts.opening_balance` itself / any `transactions` row dated before "today" but on/after `opening_balance_date` / `transactions` dated in the active reporting period / `transactions.type='adjustment'`. No extra classification column is needed; it's fully derivable from `type` + `transaction_date` vs "now."

**D10 — Rows dated before `opening_balance_date` do not move the balance** (added during Phase 1 implementation). The opening balance is a snapshot of a moment; anything dated before that moment is already embodied in it. So `AccountBalanceService::balance()` sums only rows on/after `opening_balance_date`. Backfilled history from before adoption still appears in spending reports as historical activity — it simply cannot double-count against the starting position. Without this rule, a household that backfills August spending after setting a 1 September opening balance would watch their September balance silently drift away from their real bank balance.

### 5.2 Reconciliation workflow (§18)

`ReconciliationService::reconcile($account, $reconciliationDate, $actualBalance, $note)`:
1. Compute `systemBalance = AccountBalanceService::balance($account, asOf: $reconciliationDate)`.
2. `difference = actualBalance − systemBalance`.
3. Insert a `reconciliations` row (`actual_balance`, `system_balance`, `difference`, `status = difference == 0 ? 'reconciled' : 'discrepancy'`, `note`).
4. **If `difference != 0`:** the UI surfaces the gap and *offers* — never auto-applies — an Adjustment. Only on explicit user confirmation does `ReconciliationService::postAdjustment($reconciliation, $requiredNote)` create a `transactions` row (`type=adjustment`, `account_id`, `amount=ABS(difference)`, `balance_effect` chosen to move the system balance toward `actual_balance`, `reconciliation_id` set), then recalculates the account and marks the reconciliation `status='resolved'` with `adjustment_transaction_id` set.

This satisfies §18's "never silently overwrite calculated balances to make them match" — the calculated balance is never directly edited; a new, visible, notated ledger row is what changes it, and it always shows up in that account's transaction history and drill-down.

---

## 6. Reporting Metric Definitions

All periods are `[start, end]` inclusive date ranges. All queries implicitly add `AND deleted_at IS NULL` (handled automatically by Eloquent `SoftDeletes` scopes).

### 6.1 Total Spending (§26)

```sql
SELECT
  (SELECT COALESCE(SUM(amount),0) FROM transactions
     WHERE type = 'expense' AND transaction_date BETWEEN :start AND :end)
  +
  (SELECT COALESCE(SUM(interest_component),0) FROM loan_payments
     WHERE payment_date BETWEEN :start AND :end)
AS total_spending;
```
Excludes (by construction, none of these rows are `type='expense'` or in the interest sum): Transfers, Credit-card bill payments, Loan principal, Opening balance adjustments, and (per D9) Asset Purchases.

### 6.2 Cash Outflow (§26)

Real money leaving asset accounts (bank/cash/investment) — broader than Total Spending, includes CC payments, loan repayments (full EMI, not just interest), transfers out, and investment moves:

```sql
SELECT COALESCE(SUM(t.amount),0) AS cash_outflow
FROM transactions t
JOIN accounts a ON a.id = t.account_id
WHERE a.normal_balance = 'asset'
  AND t.balance_effect = 'decrease'
  AND t.type IN ('expense','transfer','liability_payment','asset_purchase')
  AND t.transaction_date BETWEEN :start AND :end;
```
(Credit-card *expense* legs are excluded automatically — they post to a `liability` account, not an `asset` account.)

### 6.3 Credit-Card Spending (§26 / §19F)

```sql
SELECT cc.id AS credit_card_id, SUM(t.amount) AS card_spending
FROM transactions t
JOIN credit_cards cc ON cc.account_id = t.account_id
WHERE t.type = 'expense' AND t.transaction_date BETWEEN :start AND :end
GROUP BY cc.id;
```
Reported separately from `credit_card_payments.amount` for the same period (§19F requirement).

### 6.4 Net Cash Movement (§26)

```sql
SELECT
  (SELECT COALESCE(SUM(t.amount),0) FROM transactions t JOIN accounts a ON a.id=t.account_id
     WHERE a.normal_balance='asset' AND t.balance_effect='increase'
       AND t.transaction_date BETWEEN :start AND :end)
  -
  (SELECT COALESCE(SUM(t.amount),0) FROM transactions t JOIN accounts a ON a.id=t.account_id
     WHERE a.normal_balance='asset' AND t.balance_effect='decrease'
       AND t.transaction_date BETWEEN :start AND :end)
AS net_cash_movement;
```
Equivalent to Σ(asset account balance changes) for the period — inherently reconciles with Cash Inflow minus Cash Outflow.

### 6.5 Net Worth (§19J / §26)

```sql
SELECT
  (SELECT COALESCE(SUM(cached_balance),0) FROM accounts WHERE normal_balance='asset' AND is_active=1)
  + (SELECT COALESCE(SUM(COALESCE(current_value, purchase_value)),0) FROM assets)
  - (SELECT COALESCE(SUM(cached_balance),0) FROM accounts WHERE normal_balance='liability' AND is_active=1)
  - (SELECT COALESCE(SUM(outstanding_principal),0) FROM loans WHERE status='active')
AS net_worth;
```

### 6.6 Dashboard / Upcoming / Financial Reality (§15, §21)

`UpcomingObligationsService::forNextDays($n)` unions three estimated/confirmed sources, each tagged:

```sql
-- Recurring (estimated unless already confirmed via recurring_transaction_occurrences)
SELECT 'recurring' AS source, rt.name, rt.amount, rto.due_date,
       (rto.status = 'confirmed') AS is_confirmed
FROM recurring_transaction_occurrences rto
JOIN recurring_transactions rt ON rt.id = rto.recurring_transaction_id
WHERE rto.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :n DAY)
  AND rto.status != 'skipped'

UNION ALL
-- Credit card statements due
SELECT 'credit_card', cc.card_name, ccs.statement_amount + ccs.carried_balance, ccs.due_date, 0
FROM credit_card_statements ccs JOIN credit_cards cc ON cc.id = ccs.credit_card_id
WHERE ccs.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :n DAY)
  AND ccs.status NOT IN ('paid')

UNION ALL
-- Loan EMIs (computed due date from due_day)
SELECT 'loan', l.name, l.emi_amount, <next_due_date_from(l.due_day)>, 1
FROM loans l WHERE l.status = 'active'
  AND <next_due_date_from(l.due_day)> BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :n DAY);
```

**Financial Reality (§21):**
```
current_bank_cash       = Σ(accounts.cached_balance WHERE type IN ('bank','cash'))
committed               = Σ(UpcomingObligationsService::forNextDays(30).amount)
realistically_available = current_bank_cash - committed
```
Items sourced from `recurring_transaction_occurrences` in `pending` status, or from `credit_card_statements` not yet `generated` for a future cycle, are rendered with an "estimated" badge in the UI (§21's "clearly labeled estimated/forecast amounts"); loan EMIs and already-`generated` CC statements are rendered as confirmed amounts.

### 6.7 Monthly comparison (§20) and weekly (§19B)

Both are the same Total-Spending/category queries (§6.1, plus a `category_id` GROUP BY) re-run per calendar week or month; MySQL-side pivoting is avoided — the controller issues one query returning `(period_label, category, amount)` rows and pivots into a grid in a PHP Collection before handing to Blade/Chart.js. This keeps every period figure defined by the *same* underlying metric function, so a monthly total and a dashboard total for the same month can never silently disagree.

### 6.8 Planned vs Unplanned (§19G), Payer/Beneficiary (§19D/E), Account analysis (§19F)

All are `SUM(transactions.amount) WHERE type='expense' AND transaction_date BETWEEN ... GROUP BY planned_status | payer_id | beneficiary_id | account_id` — the same base "Total Spending" row set, just grouped differently, which is what makes drill-down (§27 "Explainable") straightforward: every summary number is one `WHERE`+`GROUP BY` variant of one canonical query, and clicking it re-runs the ungrouped version scoped to that filter to list the underlying `transactions` rows.

---

## 7. Laravel Model/Service Architecture

### 7.1 Proposed `app/` structure

```
app/
  Models/
    Account.php
    Category.php
    Person.php
    Merchant.php
    Tag.php
    Transaction.php
    TransactionSplit.php
    CreditCard.php
    CreditCardStatement.php
    CreditCardStatementItem.php
    CreditCardPayment.php
    Loan.php
    LoanPayment.php
    RecurringTransaction.php
    RecurringTransactionOccurrence.php
    Asset.php
    Reconciliation.php
  Enums/
    TransactionType.php          (native PHP 8.1+ enum)
    LegRole.php
    BalanceEffect.php
    PlannedStatus.php
    Purpose.php
    AccountType.php
    NormalBalance.php
  Services/
    AccountBalanceService.php       // balance()/recalculate() — sole owner of sign logic
    TransactionService.php          // recordExpense(), recordIncome(), recordAdjustment(), voidTransaction()
    TransferService.php             // createTransfer(), updateTransfer(), voidTransfer()
    CreditCardService.php           // generateStatement(), pay(), outstanding(), availableCredit()
    LoanService.php                 // recordPayment(), updatePayment(), recalcOutstanding()
    AssetService.php                // recordPurchase()
    RecurringTransactionService.php // generateOccurrences(), confirmOccurrence(), skipOccurrence()
    ReconciliationService.php       // reconcile(), postAdjustment()
    Reporting/
      SpendingReportService.php     // totalSpending(), cashOutflow(), creditCardSpending(), netCashMovement()
      NetWorthService.php
      UpcomingObligationsService.php
      DashboardService.php          // composes the above for the §15 layout
  Http/
    Controllers/
      DashboardController.php
      TransactionController.php     // thin: validate via FormRequest, call *Service, redirect
      AccountController.php
      CreditCardController.php
      LoanController.php
      AssetController.php
      RecurringTransactionController.php
      ReconciliationController.php
      ReportController.php
      Settings/CategoryController.php
      Settings/PersonController.php
      Settings/MerchantController.php
    Requests/
      StoreExpenseRequest.php
      StoreIncomeRequest.php
      StoreTransferRequest.php
      StoreLiabilityPaymentRequest.php
      StoreAssetPurchaseRequest.php
      StoreAdjustmentRequest.php
      StoreLoanPaymentRequest.php
      StoreReconciliationRequest.php
      StoreAccountRequest.php / StoreCategoryRequest.php / etc.
```

### 7.2 Where business rules live

Controllers stay thin: validate via a Form Request, call exactly one service method, redirect/return. All accounting rules (sign conventions, linked-leg creation, void cascades, balance recompute, statement grouping, principal/interest bookkeeping) live in the `Services` layer — never in a controller or a model boot/observer implicitly. This is deliberate: financial correctness logic should be unit-testable in isolation (matching §37's test priorities) without booting HTTP.

**Balance-calculation logic** lives in `AccountBalanceService` exclusively (not scattered as model accessors), because it must be called both reactively (after every mutating transaction op) and independently (reconciliation's `systemBalance`, the recalculation artisan command, net worth reports). `Account` exposes a thin accessor `getBalanceAttribute()` that simply returns `cached_balance` for display speed; anything requiring guaranteed-fresh or as-of-date balances calls `AccountBalanceService::balance($account, $asOf)` directly.

### 7.3 Key model relationships (illustrative, not exhaustive)

- `Account hasMany Transaction`; `Account hasOne CreditCard`.
- `Transaction belongsTo Account, Category, Category (subcategory), Person (payer), Person (beneficiary), Merchant`; `hasMany TransactionSplit`; scopes: `ofType()`, `forAccount()`, `inPeriod()`, `notVoided()` (default via `SoftDeletes`).
- `CreditCard belongsTo Account`; `hasMany CreditCardStatement, CreditCardPayment`.
- `CreditCardStatement hasMany CreditCardStatementItem`; `belongsToMany Transaction through CreditCardStatementItem`.
- `Loan hasMany LoanPayment`; `LoanPayment belongsTo Loan, Account, Transaction`.
- `RecurringTransaction hasMany RecurringTransactionOccurrence`.
- `Asset belongsTo Loan (linked_loan_id), Transaction (purchase_transaction_id)`.
- `Reconciliation belongsTo Account, Transaction (adjustment_transaction_id)`.

### 7.4 Phase 1 scope (per §33)

Phase 1 = Accounts, Opening balances, Categories, People/Beneficiaries, Merchants, Transactions, Expenses, Income, Transfers, Basic balances. Concretely, ship only these migrations/models/services first, even though the full schema above is the target end-state (designing the whole ERD up front avoids FK/naming churn later):

- Migrations: `accounts`, `categories`, `people`, `merchants`, `tags`, `transaction_tag`, `transactions`, `transaction_splits`.
- `transactions.type` constrained at the Phase-1 application layer to `expense | income | transfer` only (the enum column itself already includes the full set from §1.2 so no later migration is needed — the app simply doesn't expose `asset_purchase`/`liability_payment`/`adjustment` in UI/services yet).
- Services: `AccountBalanceService`, `TransactionService::recordExpense/recordIncome`, `TransferService`.
- Everything under `credit_cards*`, `loans`/`loan_payments`, `assets`, `recurring_transactions*`, `reconciliations` is deferred to Phases 2/3/5 respectively, per the phased plan — their migrations are written when those phases start, not upfront, so Phase 1 stays reviewable and testable in isolation. `Total Spending`/`Cash Outflow` queries (§6.1/6.2) degrade gracefully in Phase 1 since the loan/CC subqueries simply return no rows until those tables exist.

Success criterion for Phase 1 (§33): a household member can record a bank expense, a credit-card expense (posting to a `type=credit_card` account — the statement/outstanding *reporting* machinery is what's deferred to Phase 2, not the ability to post), income, and a transfer between two accounts, and see correct `accounts.cached_balance` for each — with zero ambiguity about whether a transfer counted as spending.

---

## Critical Files for Implementation

- `database/migrations/*_create_accounts_table.php`, `*_create_transactions_table.php` — the two tables every other piece of accounting logic depends on; getting `balance_effect`/`normal_balance`/`transfer_group_id` right here is the crux of the whole design.
- `app/Services/AccountBalanceService.php` — sole owner of the sign-matrix (§1.3) and the recalculation/traceability guarantee (Rule 8); every other service depends on it.
- `app/Services/TransactionService.php` and `app/Services/TransferService.php` — where Rule 6 (edit consistency) and Rule 7 (soft-delete/void cascades) are actually enforced.
- `app/Services/CreditCardService.php` — implements §4's purchase/statement/payment separation (Phase 2, but its contract should be sketched early since `transactions.credit_card_payment_id`/`leg_role` are already in the Phase-1 schema).
- `app/Services/Reporting/SpendingReportService.php` — the single place the §26 metric definitions (§6 of this document) are implemented as query methods, so every dashboard/report figure is guaranteed to agree.
