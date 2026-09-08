# Personal Finance Management System

## Product & Development Specification

**Project type:** Private household finance management application  
**Primary goal:** Help two working professionals understand exactly where household money is going, what is already committed, how credit-card spending affects future cash flow, and how debt/assets affect overall financial position.

---

## 1. Product Vision

This application is **not a conventional expense tracker** and should not be designed as a generic CRUD application.

It should behave like a lightweight **household accounting and financial control system**, inspired by the way a company tracks income, expenses, accounts, liabilities, assets, commitments, cash flow, and reporting.

The core objective is financial clarity:

- Record spending when it actually happens.
- Keep credit-card spending separate from credit-card bill payment.
- Track who paid and who benefited.
- Track planned, unplanned, and emergency spending.
- Track bank/cash/card balances.
- Track loans and EMI principal/interest.
- Track upcoming financial commitments.
- Show monthly and weekly trends.
- Reconcile the application against actual bank and credit-card balances.
- Build a trustworthy history that can later be used for budgeting and financial decisions.

The application is private and intended to run locally. There is **no user-management requirement for V1**.

---

## 2. Technology Stack

Use the following stack:

- **Backend:** Laravel
- **Database:** MySQL
- **Server-rendered UI:** Blade
- **CSS/UI:** Bootstrap 5
- **Charts:** Chart.js
- **Frontend interaction:** jQuery
- **JavaScript:** Vanilla JS where practical, with jQuery for AJAX/forms/interactions
- **Authentication:** Not required for V1 because the app is private/local

Do not introduce unnecessary infrastructure such as microservices, Redis, Kubernetes, cloud hosting, OAuth, or external financial APIs in V1.

---

## 3. Core Financial Principle

The system must distinguish between these concepts:

1. **Expense** – value consumed.
2. **Income** – money/value received.
3. **Transfer** – movement of money between accounts owned/managed by the household.
4. **Liability payment** – repayment of an outstanding liability such as a credit card or loan; generally represented as a transfer plus liability reduction.
5. **Asset acquisition** – purchase/acquisition of something that should appear as an asset rather than a normal lifestyle expense.
6. **Adjustment** – controlled correction/reconciliation entry.

Do not count the same financial event twice.

---

## 4. Credit Card Accounting Rule

This is one of the most important rules in the entire application.

### Example

On 8 September:

- Shopping purchase = ₹2,000
- Payment method = HDFC Credit Card

Record:

```text
Expense:              ₹2,000
Category:             Shopping
Account:              HDFC Credit Card
Date:                 8 September
```

The expense is recognized on the purchase date.

The credit card liability increases by ₹2,000.

Later, when the credit-card statement is generated, the system should group already-recorded transactions into that statement. The statement itself is **not a new expense**.

When the user pays the card bill from the bank:

```text
Bank Account          -₹25,000
Credit Card Liability +₹25,000 cleared/reduced
```

The payment must **not create another expense**.

### Required behavior

The system must support:

- Card transactions at purchase time.
- Statement periods.
- Statement amount.
- Payment due date.
- Current outstanding.
- Available credit.
- Card payment/reconciliation.
- Linking payments to card balances/statements where appropriate.
- Reports that never double-count card purchases and card payments.

---

## 5. Household Model

The system is for a married couple managing household finances together.

It should support the following dimensions:

### Person / payer

- Me
- Wife
- Joint/Household

### Beneficiary

- Me
- Wife
- Household
- My Parents
- Wife's Parents
- Other

The application must allow the payer and beneficiary to be different.

Example:

```text
Paid by:       Me
Beneficiary:   Wife's Parents
Amount:        ₹10,000
Category:      Family Support
```

This makes it possible to answer both:

- How much did each person pay?
- How much was spent for each group/person?

Do not turn the system into a competition between spouses. The main view should remain household-oriented, with optional breakdowns by payer.

---

## 6. Categories and Dimensions

Expenses should use more than a single category.

### Suggested category hierarchy

```text
Food
  - Groceries
  - Restaurants
  - Delivery
  - Snacks

Shopping
  - Clothing
  - Electronics
  - Household
  - Personal

Travel
  - Flights
  - Hotels
  - Local Transport
  - Fuel

Utilities
  - Electricity
  - Internet
  - Mobile
  - Gas

Family
  - My Parents
  - Wife's Parents
  - Other Family

Medical
  - Doctor
  - Medicine
  - Tests
  - Emergency

Entertainment
  - Movies
  - Events
  - Subscriptions

Insurance

Education

Loan Interest

Taxes/Fees

Other
```

Categories should be configurable from Settings.

### Additional dimensions

Every applicable transaction should support:

- Category
- Subcategory
- Payer
- Beneficiary
- Merchant
- Account
- Planned status
- Purpose
- Tags
- Notes

### Purpose

Suggested values:

- Necessity
- Lifestyle
- Family
- Investment
- Debt
- Emergency
- Discretionary

### Planned status

Suggested values:

- Planned
- Unplanned
- Emergency

These fields are essential for the analytics layer.

---

## 7. Accounts

The application must maintain an account ledger.

### Account types

- Bank account
- Cash
- Credit card
- Loan/liability
- Investment account (future/optional)
- Other asset/liability accounts as required

Every account should have:

- Name
- Type
- Institution
- Opening balance
- Current/system balance
- Currency
- Status
- Notes

The system should calculate account balances from opening balances plus/minus transactions wherever practical.

---

## 8. Opening Balances and Existing Dues

Users may start using the application in the middle of an existing financial cycle.

The system must support an **Opening Financial Position** instead of forcing the user to backfill months of fake transactions.

Example:

```text
HDFC Bank              ₹85,000
ICICI Bank              ₹1,10,000
Cash                    ₹15,000

HDFC Credit Card       -₹45,000
Land Loan             -₹12,50,000
Bike Loan              -₹1,45,000
```

Opening balances establish the starting financial position.

Users may later backfill historical transactions if desired.

The system should clearly distinguish:

- Opening balance
- Historical transaction
- Current-period transaction
- Adjustment/reconciliation

---

## 9. Transactions

The core transaction model should be flexible enough to support multiple financial events without creating duplicate accounting effects.

### Minimum transaction fields

- ID
- Date/time
- Transaction type
- Amount
- Account
- Category
- Subcategory
- Payer
- Beneficiary
- Merchant
- Planned status
- Purpose
- Description
- Notes
- Reference/identifier where useful
- Created at
- Updated at

### Transaction types

At minimum:

- Expense
- Income
- Transfer
- Asset Purchase
- Liability Payment
- Adjustment

For complex financial events, use linked records/entries rather than overloading a single simple transaction row.

---

## 10. Transfers

Transfers are not expenses.

Examples:

```text
HDFC Bank → ICICI Bank
Bank → Credit Card
Bank → Investment Account
Savings → Cash
```

The UI should make transfer behavior explicit so users do not accidentally classify transfers as spending.

Transfers should preserve a link between source and destination accounts.

---

## 11. Loans

Loans must have their own module.

Supported loan fields should include:

- Loan name
- Lender
- Original principal
- Current outstanding principal
- Interest rate
- EMI amount
- Start date
- End date/tenure
- Due day
- Account used for payment
- Status
- Notes

### EMI handling

The system should ideally support splitting an EMI into:

```text
Total EMI
  ├── Principal component
  └── Interest component
```

Example:

```text
EMI                 ₹32,500
Principal           ₹24,000
Interest             ₹8,500
```

The reporting system should not treat principal repayment as ordinary lifestyle consumption.

Interest should contribute to expense reporting, while principal reduces the liability.

V1 may allow manual entry of principal/interest components if automatic amortization is not implemented initially.

---

## 12. Assets

The system should support meaningful assets so that household net worth can eventually be shown correctly.

Examples:

- Land
- Bike
- Investments
- Other major assets

Suggested asset fields:

- Name
- Type
- Purchase date
- Purchase value
- Current estimated value
- Associated liability where applicable
- Notes

Avoid turning a major asset purchase into an ordinary lifestyle expense.

---

## 13. Recurring Transactions

The system should support recurring commitments such as:

- Land EMI
- Bike EMI
- Rent
- Utilities
- Insurance
- Subscriptions
- Regular family support
- Investments
- Other scheduled commitments

Recurring transaction fields:

- Name
- Amount
- Frequency
- Next due date
- Account
- Category
- Purpose
- Payer
- Beneficiary
- Active/inactive
- Notes

The application should generate upcoming expected entries rather than silently assuming that a payment was completed.

Where practical, users should confirm or mark a scheduled transaction as completed.

---

## 14. Upcoming Commitments

Create an **Upcoming Obligations** view.

Example:

```text
09 Sep   Electricity             ₹3,200
10 Sep   Bike EMI                ₹6,500
12 Sep   Land EMI               ₹32,500
15 Sep   Parents support          ₹8,000
20 Sep   Credit Card Statement  ~₹42,000
05 Oct   Credit Card Payment    ~₹42,000
```

The system should calculate:

- Obligations in next 7 days
- Obligations in next 30 days
- Known recurring commitments
- Credit-card due amounts
- Loan EMIs
- Other manually scheduled commitments

These values should feed a **realistic available cash** calculation.

---

## 15. Core Dashboard

The dashboard should be decision-oriented rather than simply showing a transaction table.

### Example layout

```text
SEPTEMBER 2026

Income                         ₹2,40,000
Total spending                ₹1,72,400
Investments                    ₹25,000
Debt principal                 ₹30,000

-------------------------------

ESSENTIALS                     ₹68,500
LIFESTYLE                      ₹26,800
FAMILY                         ₹18,200
UNPLANNED                      ₹14,500
SHOPPING                       ₹22,300

-------------------------------

UPCOMING
Credit Card                    ₹31,200
Land EMI                       ₹32,500
Bike EMI                        ₹6,500

Upcoming 30 days               ₹84,700

-------------------------------

FINANCIAL REALITY
Current bank/cash              ₹1,35,000
Less commitments                 ₹82,000
Realistically available          ₹53,000
```

Exact visual design can differ, but these ideas should be retained.

---

## 16. Quick Entry UX

The main expense entry screen must **not** resemble a traditional long CRUD form.

The goal is to make daily recording take seconds.

### Preferred interaction

Start with:

```text
What happened?
₹ 2,000
```

Then lightweight contextual fields:

```text
[ Shopping ] [ Food ] [ Travel ] [ Bills ]

Paid using
[ HDFC Credit Card ]

For
[ Family ] [ Me ] [ Wife ] [ Parents ]

Where
[ Amazon ]

[ Save ]
```

### Smart defaults

Merchant history should learn defaults.

Example:

```text
Amazon
→ Shopping
→ HDFC Credit Card
→ Household
```

Then entering only:

```text
₹1,250 Amazon
```

should pre-fill likely values.

### Optional natural quick-entry syntax

Support a lightweight parser later, e.g.:

```text
2000 amazon shopping cc
```

or:

```text
850 dinner family hdfc
```

The parser must always show a confirmation state before saving if any interpretation is ambiguous.

Do not make natural-language parsing a dependency for V1.

---

## 17. Daily Workflow

The system should encourage a simple nightly workflow.

### Typical daily process

1. Open dashboard.
2. See today's recorded transactions.
3. Add missing expenses using Quick Entry.
4. Review today's credit-card spending.
5. Review unmatched/unreconciled items.
6. Finish in a few minutes.

The application should minimize mandatory fields and use smart defaults wherever possible.

---

## 18. Daily Reconciliation

A daily or periodic reconciliation workflow is strongly recommended.

Example:

```text
HDFC Bank
Actual balance:       ₹84,500
System balance:       ₹84,500
Status:               ✓ Reconciled

Credit Card
Actual outstanding:   ₹42,100
System outstanding:   ₹42,100
Status:               ✓ Reconciled
```

If different:

```text
Actual:               ₹84,500
System:               ₹86,200
Difference:            ₹1,700
```

The system should support entering an adjustment and recording a reconciliation note.

The application should never silently overwrite calculated balances to make them match.

---

## 19. Financial Statements / Main Reports

The application must provide reporting that behaves more like basic corporate finance reporting.

### A. Monthly income vs spending

Show:

- Total income
- Total spending
- Investments
- Debt principal repayment
- Debt interest
- Net cash movement
- Savings rate / surplus

### B. Weekly spending

Compare weeks within a month:

```text
Week 1
Week 2
Week 3
Week 4
```

Show income, expenses, discretionary spending, family spending, and credit-card spending.

### C. Category analysis

Show:

- Category totals
- Subcategory totals
- Percentage of spending
- Comparison to previous months

Use Chart.js.

### D. Payer analysis

Show spending paid by:

- Me
- Wife
- Joint/Household

### E. Beneficiary analysis

Show spending for:

- Household
- Me
- Wife
- My Parents
- Wife's Parents

### F. Account/payment-method analysis

Show:

- Cash
- Bank accounts
- Credit cards

Important: credit-card purchase volume and card payment amount must be shown separately.

### G. Planned vs unplanned

Show:

- Planned spending
- Unplanned spending
- Emergency spending

This is a high-priority report because unplanned expenses are an important concern for this household.

### H. Credit-card report

Show:

- Current outstanding
- Available limit
- Utilization percentage
- Current-cycle spending
- Statement amount
- Due amount
- Due date
- Spending by category
- Spending by merchant
- Spending trend vs previous months

### I. Loan report

Show:

- Outstanding principal
- EMI
- Principal paid
- Interest paid
- Upcoming EMI
- Loan trend over time

### J. Net worth

Basic balance sheet style:

```text
ASSETS
Bank
Cash
Investments
Land
Bike

LIABILITIES
Credit Cards
Land Loan
Bike Loan

NET WORTH = ASSETS - LIABILITIES
```

---

## 20. Monthly Comparison

Provide a multi-month view such as:

```text
                 Apr    May    Jun    Jul    Aug    Sep
Income           240    240    260    240    240    240
Food              19     21     18     25     20     24
Shopping          14     26     31     18     28     22
Travel              8     14      6     22      9     16
Family             12     11     20     10     18     18
Unplanned           5      8     12      6     14     15
```

The exact representation may use charts and tables.

The objective is to reveal behavioral patterns over time.

---

## 21. Financial Reality / Committed Cash

This should be a first-class feature.

Users should not see only their bank balance. They should also see how much is already effectively committed.

Example:

```text
Current bank + cash            ₹1,35,000

Upcoming commitments:
Land EMI                         ₹32,500
Bike EMI                          ₹6,500
Credit Card                      ₹28,000
Bills                             ₹7,000
Family                            ₹8,000
                                  -------
Committed                        ₹82,000

REALISTIC AVAILABLE              ₹53,000
```

The implementation should clearly label estimated/forecast amounts where exact amounts are not yet known.

---

## 22. Budgeting Strategy

Budgeting should **not** be the first major feature.

First collect reliable data for approximately 2–3 months.

Then allow users to define category budgets:

```text
Food             ₹20,000
Shopping         ₹15,000
Travel           ₹15,000
Entertainment     ₹8,000
Family           ₹15,000
```

Show:

- Budget
- Actual
- Variance
- Variance percentage
- Status

The application should avoid imposing arbitrary budgets before actual behavior is understood.

---

## 23. Suggested Navigation

V1 navigation:

```text
Dashboard
Quick Entry
Transactions
Accounts
Credit Cards
Loans
Assets
Recurring / Upcoming
Reports
Settings
```

Settings should include at least:

```text
Categories
People
Beneficiaries
Merchants
Accounts
Opening Balances
Transaction Rules / Defaults
```

---

## 24. Suggested Database Tables

The following is a logical starting point. The development agent may normalize or adjust the schema as needed while preserving the business rules.

### accounts

Suggested fields:

- id
- name
- type
- institution
- opening_balance
- balance_type / normal_balance where useful
- currency
- is_active
- notes
- timestamps

### transactions

Suggested fields:

- id
- transaction_date
- type
- amount
- account_id
- category_id nullable
- subcategory_id nullable
- payer_id nullable
- beneficiary_id nullable
- merchant_id nullable
- planned_status nullable
- purpose nullable
- description
- notes
- linked_transaction_id nullable
- created_by/system source where useful
- timestamps

### transaction_lines / ledger_entries

Prefer a proper ledger or linked-entry design if needed to correctly support transfers, liabilities, asset purchases, and split transactions.

The implementation must prevent double counting.

### categories

- id
- name
- parent_id nullable
- transaction_type support
- is_active
- sort_order
- timestamps

### people

- id
- name
- relationship
- role/type
- is_active
- timestamps

### beneficiaries

May be merged with people if the implementation remains clear. If separated:

- id
- name
- type
- is_active

### merchants

- id
- name
- default_category_id nullable
- default_account_id nullable
- timestamps

### credit_cards

- id
- account_id
- card_name
- credit_limit
- statement_day
- payment_due_day
- annual_fee nullable
- is_active
- timestamps

### credit_card_statements

- id
- credit_card_id
- period_start
- period_end
- statement_date
- statement_amount
- due_date
- payment_amount nullable
- status
- notes
- timestamps

### credit_card_statement_items

Use linkage to transactions where needed rather than duplicating financial events.

### loans

- id
- name
- lender
- original_principal
- outstanding_principal
- interest_rate
- emi_amount
- start_date
- end_date nullable
- due_day
- status
- notes
- timestamps

### loan_payments

- id
- loan_id
- payment_date
- total_amount
- principal_component
- interest_component
- account_id
- notes
- timestamps

### recurring_transactions

- id
- name
- amount
- frequency
- next_due_date
- account_id
- category_id nullable
- payer_id nullable
- beneficiary_id nullable
- purpose nullable
- is_active
- notes
- timestamps

### assets

- id
- name
- type
- purchase_date
- purchase_value
- current_value nullable
- linked_loan_id nullable
- notes
- timestamps

### reconciliations

Recommended fields:

- id
- account_id
- reconciliation_date
- actual_balance
- system_balance
- difference
- adjustment_transaction_id nullable
- note
- status
- timestamps

### tags / transaction_tags

Optional for V1 but useful for flexible analysis.

---

## 25. Data Integrity Rules

These rules are mandatory.

### Rule 1 — No double counting

A credit-card purchase is an expense. A later card payment is not another expense.

### Rule 2 — Transfers are not spending

Moving money between household accounts must not increase expense totals.

### Rule 3 — Principal is not ordinary expense

Loan principal reduces liability. Loan interest is an expense.

### Rule 4 — Opening balances are not current-period expenses

Starting the application with existing dues must not distort the current month's spending.

### Rule 5 — Historical dates must be supported

Users can enter old transactions without changing the semantic distinction between historical and current-period records.

### Rule 6 — Editing must preserve accounting consistency

Changing a transaction should correctly update all linked balances/ledgers.

### Rule 7 — Deletion should be controlled

Prefer soft deletion or reversal/adjustment for financially material records rather than destructive deletion.

### Rule 8 — Every balance should be traceable

A displayed balance should be explainable through opening balance plus ledger activity.

---

## 26. Reporting Definitions

To avoid misleading reports, define metrics explicitly.

### Total Spending

Ordinary expenses recognized during the selected period.

Exclude:

- Transfers
- Credit-card bill payments
- Loan principal repayment
- Opening balance adjustments

Include:

- Expense transactions
- Loan interest
- Appropriate fees/charges

### Cash Outflow

Actual cash leaving bank/cash accounts during the period.

This can include:

- Expenses paid from bank/cash
- Credit-card payments
- Loan repayments
- Transfers to other accounts
- Investments

Therefore cash outflow is not equal to spending.

### Credit-Card Spending

Value of purchases posted/recorded against credit cards in the selected period.

### Net Cash Movement

Cash/in-bank inflows minus cash/in-bank outflows for the period.

### Net Worth

Assets minus liabilities.

---

## 27. UX Principles

The UI should follow these principles:

### Fast

Daily entry should take a few seconds.

### Contextual

Only show fields relevant to the selected transaction type.

### Human-readable

Use language such as:

```text
Spent
Received
Moved
Owe
Upcoming
Available
```

rather than accounting jargon wherever possible.

### Explainable

Users should be able to click a number and drill down to the transactions behind it.

### Safe

Financial numbers should be easy to verify.

### Responsive

Bootstrap 5 layout should work comfortably on laptop and smaller screens.

---

## 28. Suggested UI Components

Use Bootstrap 5 components consistently.

Recommended reusable components:

- Summary cards
- Date range selector
- Transaction quick-entry modal/panel
- Account selector
- Category selector
- Merchant autocomplete
- Person/beneficiary chips
- Status badges
- Data tables with filters
- Offcanvas/filter panels
- Confirmation modals
- Chart cards
- Empty states
- Reconciliation panels

Use jQuery AJAX for lightweight interactions where it improves usability.

---

## 29. Chart Requirements

Use Chart.js.

Required chart types where appropriate:

- Monthly spending trend: line/bar
- Category distribution: doughnut
- Weekly spending: bar
- Income vs spending: grouped bar
- Planned vs unplanned: doughnut/bar
- Credit-card spending trend: line/bar
- Loan outstanding trend: line
- Net worth trend: line

Every chart should have a corresponding tabular/drill-down representation for accuracy and accessibility.

---

## 30. Search and Filters

Transactions must support filters for:

- Date range
- Transaction type
- Account
- Category
- Subcategory
- Payer
- Beneficiary
- Merchant
- Planned status
- Purpose
- Amount range
- Credit card
- Loan

Search should support free text across description/merchant/notes where practical.

---

## 31. Import / Export

V1 should include simple backup/export capability because this is a private local system.

Recommended:

- CSV export for transactions
- CSV export for reports where useful
- Database backup guidance
- Optional JSON export/import for application data

Do not depend on external cloud storage.

---

## 32. Privacy and Local Operation

The system is intended to run on the user's laptop.

Requirements:

- No external financial API is required.
- No data should be sent to third-party services by default.
- No user-management complexity is required for V1.
- Keep secrets/configuration in `.env`.
- Provide clear local setup instructions.

Consider adding a simple local application lock later, but it is not required for the first implementation.

---

## 33. Development Phases

### Phase 1 — Financial Core

Implement:

- Accounts
- Opening balances
- Categories
- People/beneficiaries
- Merchants
- Transactions
- Expenses
- Income
- Transfers
- Basic balances

Success criterion: daily household spending can be entered without ambiguity.

### Phase 2 — Credit Cards

Implement:

- Credit cards
- Purchase transactions
- Statements
- Due dates
- Outstanding calculation
- Payments
- Credit-card reconciliation

Success criterion: card spending and card payments never double-count.

### Phase 3 — Loans and Recurring Commitments

Implement:

- Loans
- EMI schedule
- Principal/interest split
- Recurring transactions
- Upcoming obligations

Success criterion: the user can see upcoming mandatory commitments.

### Phase 4 — Dashboard and Reports

Implement:

- Monthly dashboard
- Weekly reports
- Category reports
- Payer/beneficiary reports
- Planned vs unplanned
- Credit-card analytics
- Loan analytics
- Cash flow
- Net worth

Success criterion: the application answers "where did our money go?" without manual calculation.

### Phase 5 — Reconciliation and Data Quality

Implement:

- Account reconciliation
- Difference detection
- Adjustment workflow
- Better audit/history behavior
- Imports/exports

Success criterion: the system can be trusted against real bank/card balances.

### Phase 6 — Budgeting and Intelligence

Only after sufficient real data exists:

- Budgets
- Monthly targets
- Variance alerts
- Spending anomaly indicators
- Merchant defaults
- Quick-entry parsing
- Trend-based suggestions

---

## 34. MVP Acceptance Criteria

The first usable version must allow the household to:

1. Enter an expense in a few seconds.
2. Record an expense against a bank account or credit card.
3. Record income.
4. Record transfers without counting them as expenses.
5. Record current opening balances and existing liabilities.
6. See account balances.
7. Record credit-card purchases when the purchase occurs.
8. See credit-card outstanding separately from card payments.
9. Record card bill payment without creating another expense.
10. Distinguish payer from beneficiary.
11. Distinguish planned from unplanned spending.
12. View monthly expense totals.
13. View weekly expense totals.
14. View category-wise spending.
15. View spending by payer and beneficiary.
16. View payment-method/account analysis.
17. View upcoming commitments.
18. View a realistic available amount after committed obligations.
19. View basic loan information and EMI impact.
20. Reconcile bank/card balances.
21. Export the transaction data.
22. Drill from report numbers into the underlying transactions.

---

## 35. Important Product Decisions

These decisions should not be changed casually during implementation:

### Decision A

**Expense is recorded when the purchase happens.**

### Decision B

**Credit-card bill payment is a liability-clearing event, not a new expense.**

### Decision C

**Transfers do not count as expenses.**

### Decision D

**Opening balances handle existing financial position when the app is started.**

### Decision E

**Payer and beneficiary are separate dimensions.**

### Decision F

**Principal repayment and interest are treated differently.**

### Decision G

**Reports must distinguish spending, cash flow, and balance sheet position.**

### Decision H

**The daily entry UX must be faster and simpler than a conventional form.**

---

## 36. Recommended Development Approach for the Agent

Before implementing UI-heavy work, the development agent should first produce:

1. Database ERD / relationship design.
2. Transaction lifecycle design.
3. Credit-card accounting flow.
4. Loan/EMI accounting flow.
5. Opening balance and reconciliation strategy.
6. Reporting metric definitions.
7. Laravel model/service architecture.

Only after those are approved/consistent should the agent move deeply into UI implementation.

The most important priority is **correct financial behavior**, not visual polish.

---

## 37. Testing Priorities

Create automated tests for financial invariants.

At minimum test:

### Credit-card purchase

```text
Expense increases
Card liability increases
Bank balance unchanged
```

### Credit-card payment

```text
Bank decreases
Card liability decreases
Expense total unchanged
```

### Bank-to-bank transfer

```text
Source decreases
Destination increases
Expense unchanged
```

### Loan EMI

```text
Bank decreases by EMI
Principal liability decreases by principal component
Interest expense increases by interest component
```

### Opening balance

```text
Starting position is preserved
Current-period expense reports remain clean
```

### Split expense

A transaction can be split across categories/beneficiaries if required.

### Editing

Editing a financial transaction must correctly recalculate all affected values.

### Deletion/reversal

Deleted/reversed entries must not leave orphaned balance effects.

---

## 38. Future Features (Not Required for V1)

Potential future enhancements:

- Bank statement import
- Credit-card statement import
- SMS/email statement parsing
- Receipt upload/OCR
- PWA/mobile-friendly offline mode
- Desktop packaging
- Local notifications
- Advanced forecasting
- Savings goals
- Investment portfolio tracking
- Tax planning
- Multi-currency
- What-if scenarios
- Cash-flow forecasting
- Natural-language financial queries

Do not build these before the core accounting model is stable.

---

## 39. Final Product Outcome

When the system is working correctly, the household should be able to open the application and answer, within a minute:

```text
How much did we earn this month?
How much have we actually spent?
Where did it go?
How much was planned vs unplanned?
How much did each of us pay?
How much went to each family group?
How much was spent through credit cards?
How much do we currently owe on the cards?
How much are our loans costing us?
What payments are coming next?
How much money is realistically available?
What do we own?
What do we owe?
What is our current net worth?
How does this month compare with previous months?
```

The application's success is measured by whether it gives reliable answers to those questions with minimal daily effort.

---

## 40. Guiding Principle for Development

> **Build the financial model first. Build the dashboard second.**
>
> A beautiful dashboard on top of an incorrect accounting model is worse than a simple interface with trustworthy numbers.

The system should optimize for **accuracy, speed of entry, traceability, reconciliation, and useful financial insight**.
