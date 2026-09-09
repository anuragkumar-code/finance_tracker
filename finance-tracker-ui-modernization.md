# Finance Tracker --- UI Modernization Plan

## Objective

Modernize the existing **Laravel + Blade + Bootstrap + vanilla HTML/JS**
Finance Tracker UI so it feels like a modern SaaS/productivity
application with the visual quality and interaction patterns associated
with **shadcn/ui + Radix UI**, while **keeping Laravel Blade as the
rendering layer**.

The goal is **not** to convert the application to React, Vue, Inertia,
or another frontend framework.

The existing functionality, routes, forms, business rules, database
behavior, and financial calculations must remain intact. This is
primarily a **frontend design-system and UX modernization project**.

------------------------------------------------------------------------

# 1. Current Application

The application currently contains these major areas:

-   Dashboard
-   Quick Entry
-   Transactions
-   Accounts
-   Credit Cards
-   Loans
-   Recurring
-   Assets
-   Upcoming
-   Reports
-   Budgets
-   Reconcile
-   Settings
    -   Categories
    -   People
    -   Merchants

The current UI uses:

-   Laravel Blade
-   Bootstrap
-   HTML/CSS
-   Vanilla JavaScript
-   Server-rendered pages
-   Existing forms and server-side logic

The screenshots show a functional finance-management application, but
visually it currently resembles a traditional Bootstrap/admin
application.

The main modernization targets are:

1.  Typography
2.  Spacing
3.  Colors
4.  Cards
5.  Buttons
6.  Forms
7.  Tables
8.  Navigation/sidebar
9.  Badges
10. Dropdowns
11. Modals/dialogs
12. Empty states
13. Charts
14. Responsive behavior
15. Visual hierarchy
16. Interaction states
17. Accessibility

------------------------------------------------------------------------

# 2. Target Design Direction

The target should feel like:

> **A premium modern personal-finance SaaS application --- clean, calm,
> information-dense, highly readable, and professional.**

Use shadcn/ui as a **visual/design reference**, not as a literal
dependency.

The UI should have:

-   Neutral backgrounds
-   Subtle borders
-   Minimal shadows
-   Rounded corners
-   Strong typography hierarchy
-   Compact but comfortable spacing
-   Consistent component dimensions
-   Modern form controls
-   Subtle hover states
-   Clear focus states
-   Carefully controlled use of color
-   Excellent data readability
-   Responsive layouts
-   Consistent iconography

Avoid making it look like:

-   Generic Bootstrap
-   Old enterprise software
-   Material Design
-   A colorful dashboard template
-   A neumorphism UI
-   A glassmorphism UI
-   A heavily animated application

The application is finance software, so **clarity and trust are more
important than visual decoration**.

------------------------------------------------------------------------

# 3. Recommended Frontend Stack

## Primary recommendation

Use:

-   **Tailwind CSS**
-   **Alpine.js**
-   **Lucide Icons**
-   **Chart.js**
-   **Laravel Blade**

This gives the project a modern component-driven UI without introducing
React.

### Tailwind CSS

Tailwind should become the primary styling system.

It is recommended to gradually remove Bootstrap dependency from the
application rather than doing a risky all-at-once rewrite.

Tailwind should control:

-   Layout
-   Spacing
-   Typography
-   Colors
-   Borders
-   Radius
-   Shadows
-   Responsive behavior
-   States
-   Form styling

## Alpine.js

Use Alpine.js for lightweight interaction:

-   Dropdowns
-   Dialogs
-   Modals
-   Tabs
-   Collapsible sections
-   Mobile sidebar
-   Command-style search
-   Toasts
-   Confirmation dialogs
-   Popovers
-   Toggle states
-   Quick Entry interactions

Do not introduce a large SPA framework.

## Lucide Icons

Use Lucide icons instead of manually drawn SVGs or inconsistent icon
libraries.

Every icon should have a consistent:

-   Stroke width
-   Size
-   Alignment
-   Visual weight

Examples:

-   Dashboard → LayoutDashboard
-   Transactions → ArrowLeftRight
-   Accounts → Wallet
-   Credit Cards → CreditCard
-   Loans → Landmark
-   Recurring → Repeat
-   Assets → Building2
-   Reports → ChartNoAxesCombined
-   Budgets → ChartPie
-   Reconcile → Scale
-   Settings → Settings
-   Add → Plus
-   Edit → Pencil
-   Delete → Trash2
-   Search → Search
-   Filter → SlidersHorizontal
-   Calendar → Calendar
-   More → MoreHorizontal

------------------------------------------------------------------------

# 4. Optional Libraries

New libraries are allowed if they materially improve the UI.

Preferred additions:

### Tailwind CSS

Required/recommended as the primary styling layer.

### Alpine.js

Recommended for Blade-friendly interactivity.

### Lucide

Recommended for consistent icons.

### Chart.js

Recommended for:

-   Spending trends
-   Category breakdown
-   Cash-flow charts
-   Monthly comparisons
-   Budget progress
-   Net-worth charts

### Flatpickr

Optional.

Use it if native browser date inputs are not sufficient for:

-   Transaction filtering
-   Date ranges
-   Recurring dates
-   Reports
-   Reconciliation

### Floating UI

Optional.

Use only if custom popovers/tooltips/menus require robust positioning.

Do not add libraries simply because they are popular.

------------------------------------------------------------------------

# 5. Important: Do NOT Use React shadcn/ui Directly

Do not attempt to install the normal React version of shadcn/ui and
force it into Blade.

Instead:

> Recreate the **design language and component philosophy** of shadcn/ui
> using Blade + Tailwind + Alpine.js.

The project should effectively develop its own:

``` text
resources/views/components/ui/
```

component system.

For example:

``` text
resources/views/components/ui/button.blade.php
resources/views/components/ui/input.blade.php
resources/views/components/ui/select.blade.php
resources/views/components/ui/card.blade.php
resources/views/components/ui/badge.blade.php
resources/views/components/ui/dialog.blade.php
resources/views/components/ui/dropdown.blade.php
resources/views/components/ui/table.blade.php
resources/views/components/ui/alert.blade.php
resources/views/components/ui/tabs.blade.php
resources/views/components/ui/empty-state.blade.php
```

This gives the application a shadcn-like component architecture while
remaining native to Blade.

------------------------------------------------------------------------

# 6. Design Tokens

Create a centralized design system.

Do not scatter arbitrary colors and spacing values throughout Blade
templates.

Define tokens for:

## Colors

Use a neutral/slate base.

Suggested semantic colors:

``` text
background
foreground
card
card-foreground
muted
muted-foreground
border
input
primary
primary-foreground
secondary
secondary-foreground
accent
accent-foreground
destructive
destructive-foreground
success
warning
info
```

Finance-specific semantic colors:

``` text
income
expense
debt
positive
negative
planned
unplanned
```

Suggested visual direction:

-   Background: very light neutral/slate
-   Cards: white
-   Borders: subtle gray
-   Primary: dark neutral or restrained blue
-   Income: green
-   Expense/debt: red
-   Warning: amber
-   Secondary text: muted gray

Do not overuse red and green.

Use them primarily for financial meaning.

------------------------------------------------------------------------

# 7. Typography

Replace the current Bootstrap-style typography with a stronger SaaS
hierarchy.

Recommended font:

-   Inter
-   Geist
-   Plus Jakarta Sans

Prefer **Inter** if simplicity and availability are priorities.

Typography hierarchy:

``` text
Page title
Section title
Card title
Metric
Body
Secondary text
Helper text
Metadata
Table text
```

Examples:

Page title:

``` text
text-2xl / font-semibold
```

Large metric:

``` text
text-2xl or text-3xl / font-semibold
```

Secondary metadata:

``` text
text-sm / text-muted-foreground
```

Avoid excessive uppercase text.

Uppercase labels should only be used for compact financial metric labels
where useful.

------------------------------------------------------------------------

# 8. Global Layout

The current sidebar + large content area should be retained
conceptually, but modernized.

## Desktop

Use:

``` text
┌──────────────┬────────────────────────────────────┐
│              │                                    │
│    Sidebar   │        Main Content                │
│              │                                    │
│              │                                    │
└──────────────┴────────────────────────────────────┘
```

Sidebar:

-   Width around 240--260px
-   Dark/slate neutral background
-   Subtle border
-   Logo/application name at top
-   Grouped navigation
-   Active navigation item with subtle filled background
-   Icons
-   Hover states
-   Settings section visually separated

Main content:

-   Maximum readable width
-   Responsive horizontal padding
-   Consistent vertical rhythm

Do not use excessive empty whitespace.

The current screenshots contain very large unused areas on several
pages. Modernize the layout so information is presented efficiently.

------------------------------------------------------------------------

# 9. Sidebar

Current sidebar is functional but visually dated.

Redesign it as a modern SaaS navigation.

Structure:

``` text
Finance Tracker

MAIN
Dashboard
Quick Entry
Transactions
Accounts
Credit Cards
Loans
Recurring
Assets
Upcoming

ANALYTICS
Reports
Budgets
Reconcile

SETTINGS
Categories
People
Merchants
```

Each navigation item should contain:

-   Icon
-   Label
-   Active state

Active state:

-   Subtle contrasting background
-   Slightly stronger text
-   Rounded-md
-   No excessive glow

Hover:

-   Smooth background transition
-   No dramatic animation

Mobile:

-   Sidebar becomes a drawer
-   Hamburger button opens it
-   Overlay closes it

------------------------------------------------------------------------

# 10. Page Headers

Current page headers should become more structured.

Example:

``` text
Transactions

Manage and review all your income, spending and transfers.

                         + Income   + Transfer   + Spend
```

Page header should support:

-   Title
-   Description
-   Primary action
-   Secondary actions
-   Filters where appropriate

Buttons should align vertically and have consistent height.

------------------------------------------------------------------------

# 11. Cards

Cards are heavily used throughout the application.

Create one reusable card component.

Recommended:

``` text
rounded-xl
border
bg-card
shadow-sm
```

But keep shadows extremely subtle.

Avoid:

``` text
heavy box-shadow
large gradients
3D effects
```

Card anatomy:

``` text
Card
 ├── Header
 │    ├── Title
 │    └── Action
 ├── Content
 └── Footer
```

Blade usage should ideally look like:

``` blade
<x-ui.card>
    ...
</x-ui.card>
```

or equivalent project convention.

------------------------------------------------------------------------

# 12. Financial Metric Cards

The Dashboard currently contains:

-   Received
-   Spent
-   On Credit Cards
-   Realistically Available

Modernize them into compact financial summary cards.

Example:

``` text
┌─────────────────────────────┐
│ RECEIVED                    │
│                             │
│ ₹0.00                       │
│ No income recorded          │
└─────────────────────────────┘
```

Important:

-   Large number
-   Small label
-   Optional comparison/helper text
-   Semantic color only when useful
-   Avoid giant cards

Metrics should be scannable in 1--2 seconds.

------------------------------------------------------------------------

# 13. Buttons

Create standardized button variants.

Required:

``` text
primary
secondary
outline
ghost
destructive
link
```

Sizes:

``` text
sm
md
lg
icon
```

Examples:

``` text
+ Spend
+ Income
+ Transfer
Save
Cancel
Edit
Delete
```

Primary action:

-   Strong but not oversized
-   Rounded-md
-   Consistent height
-   Clear hover/focus states

Do not use Bootstrap `.btn` classes in the new design system.

------------------------------------------------------------------------

# 14. Form Controls

The current Quick Entry page contains many pills and Bootstrap-style
controls.

Modernize:

-   Inputs
-   Selects
-   Date inputs
-   Textareas
-   Search fields
-   Currency inputs
-   Radio/select controls
-   Toggle controls

All controls should have:

-   Consistent height
-   Consistent radius
-   Subtle border
-   Clear focus ring
-   Clear disabled state
-   Accessible label
-   Helper/error text

Recommended default:

``` text
height: 40px
rounded-md
border
px-3
text-sm
```

Focus:

``` text
ring-2
ring-primary/20
border-primary
```

------------------------------------------------------------------------

# 15. Quick Entry Page

This is one of the most important screens and should receive special
attention.

The current screen asks:

> What did you spend?

The modern version should feel like a focused transaction-entry
workflow.

Suggested structure:

``` text
What did you spend?

Amount and where it came from is all that is needed.
Everything else is optional.

┌─────────────────────────────────────┐
│ ₹ 0                                 │
└─────────────────────────────────────┘

Where did the money come from?

[Scapia] [Kotak(4785)] [BOB Bank] ...

Where did you buy it?

[ Search merchant... ]

What kind of spend?

[Food] [Shopping] [Travel] ...

Who paid?

[Anurag] [Khushboo] [Household]

Who was it for?

[Anurag] [Khushboo] [Household] ...

                    [Save transaction]
```

Important UX improvements:

-   Amount should be visually dominant
-   Recently used accounts should be easy to select
-   Chips should look modern
-   Selected chips need a clear state
-   Optional fields should visually recede
-   Form should work well with keyboard
-   Save action should remain obvious

------------------------------------------------------------------------

# 16. Transactions Page

The current table is functional but visually dated.

Modernize it with:

-   Cleaner table header
-   Better row spacing
-   Subtle hover
-   Better amount alignment
-   Category metadata
-   Account metadata
-   Status badges
-   Responsive behavior

Example:

``` text
Date       Details             Category     Account      Amount

09 Sep     teez                Shopping     Scapia       ₹306
           Spent

09 Sep     office bazar        Food         Scapia       ₹120
           Spent

09 Sep     Thar Retraunt       Food         Kotak        ₹863
           Spent Planned
```

Amounts should be right aligned.

Negative/expense values should use restrained semantic styling.

Do not make every amount bright red.

------------------------------------------------------------------------

# 17. Transaction Filters

Create a modern filter toolbar.

Desktop:

``` text
[Date range] [Type] [Account] [Category] [Search] [Filters]
```

Advanced filters can appear in a popover/drawer.

Mobile:

``` text
[Search]
[Filters]
```

with filters opening in a sheet/dialog.

Buttons:

``` text
Apply
Clear
```

should be visually secondary to the main page action.

------------------------------------------------------------------------

# 18. Accounts Page

The current Accounts page contains a large table.

Improve:

-   Summary cards at top
-   Account grouping
-   Better ownership badges
-   Better institution metadata
-   Currency alignment
-   Current balance emphasis
-   Edit action as icon/button
-   Clear distinction between Bank Accounts and Credit Cards
-   Set Aside section should look intentionally separate

Example summary:

``` text
You own
₹51,840.33

You owe
₹27,31,375.00

Net worth
-₹26,79,534.67
```

Use semantic styling carefully.

------------------------------------------------------------------------

# 19. Credit Cards Page

Credit cards should use modern cards instead of dense Bootstrap panels.

Each card can have:

``` text
BOB Card                         ₹300 owed
Bank of Baroda

1.2% of limit used

████░░░░░░░░░░░░░░

₹24,700 available

[Pay bill]             Details
```

Progress bars should be:

-   Thin
-   Rounded
-   Semantic
-   Visually subtle

Important states:

-   Normal
-   High utilization
-   Payment due soon
-   Overdue

Use badges for these states.

------------------------------------------------------------------------

# 20. Loans Page

Loan cards should emphasize:

-   Loan name
-   Owner
-   Lender
-   EMI
-   Paid
-   Remaining
-   Progress
-   Next due date
-   End date

Example:

``` text
Bike loan                         Running

₹16,018 EMI

Paid                  Remaining
₹32K                  ₹1.6L

2 of 12 EMIs

████░░░░░░░░░░░

Ends Jul 2027
```

Make progress visually understandable.

Avoid excessive decoration.

------------------------------------------------------------------------

# 21. Recurring Commitments

The current recurring screen is very form-heavy.

Improve it using:

-   Commitment cards/table
-   Clear next due date
-   Frequency badge
-   Amount emphasis
-   Account
-   Category
-   Owner
-   Edit action

The add form should use a clean two-column layout on desktop and one
column on mobile.

Optional fields should remain visually secondary.

------------------------------------------------------------------------

# 22. Assets

Assets should clearly distinguish:

-   Asset value
-   Loan attached to asset
-   Owner
-   Acquisition date
-   Asset type

For example:

``` text
Land

Land / Property
Khushboo

₹— value not set

Bought with Land Loan
₹25,35,325 still owed

Edit
```

Use an informational alert for the explanation about unvalued assets.

------------------------------------------------------------------------

# 23. Reports

The Reports page is the biggest opportunity for visual improvement.

Current charts are functional but basic.

Use Chart.js with modern styling.

Required visualizations:

### Spending over time

Bar/line chart.

### Spending by category

Donut chart.

### Planned vs unplanned

Horizontal bars or compact comparison.

### Who paid

Horizontal bar.

### Paid with

Horizontal bar.

### Subcategories

Horizontal bars.

### Top merchants

Horizontal bars.

Charts should:

-   Have clean typography
-   Use minimal grid lines
-   Avoid unnecessary legends
-   Have useful tooltips
-   Use consistent semantic colors
-   Be responsive

Do not create chart-heavy dashboards where every section becomes a
visualization.

------------------------------------------------------------------------

# 24. Reconcile Page

The current Reconcile screen is very functional but looks like a legacy
admin form.

Modernize it substantially.

Account list:

``` text
BOB Bank
Bank Account

App says                 ₹30,054.27
Last checked             Never
Status                   Not checked
```

Use status badges:

``` text
Not checked
Matched
Difference
```

The "Check an account" panel should become a polished card/dialog
workflow.

When a difference exists:

``` text
Difference found

App balance       ₹30,054.27
Bank balance      ₹29,850.00
Difference        -₹204.27
```

Do not silently modify balances.

Keep the current accounting behavior intact.

------------------------------------------------------------------------

# 25. Upcoming

Upcoming commitments should use a timeline/list-oriented design.

Example:

``` text
03 Oct

Bike loan                              ₹16,018
EMI · Loan

Home Rent                              ₹19,700
Rent · Recurring

07 Oct

Land Loan                              ₹39,005
EMI · Loan

Broadband                               ₹1,178
Internet · Recurring
```

Group upcoming items by date.

Use badges for:

-   Estimate
-   Loan
-   Recurring
-   Credit Card

------------------------------------------------------------------------

# 26. Badges

Create a consistent badge system.

Variants:

``` text
default
secondary
success
warning
destructive
outline
```

Examples:

``` text
Spent
Planned
Running
Estimate
Not checked
Matched
Difference
```

Badges should be compact and not overly saturated.

------------------------------------------------------------------------

# 27. Tables

Create one standardized table component.

Requirements:

-   Sticky header where useful
-   Hover state
-   Proper numeric alignment
-   Responsive behavior
-   Empty state
-   Loading state
-   Pagination if required
-   Sort indicators
-   Accessible headers

On mobile, tables may transform into cards where appropriate.

Do not force wide desktop tables onto small screens.

------------------------------------------------------------------------

# 28. Dialogs / Modals

Replace Bootstrap modal UI with a custom Alpine.js dialog component.

Required behaviors:

-   Keyboard accessible
-   Escape closes
-   Focus management
-   Backdrop
-   Scroll locking
-   Mobile-friendly
-   Clear close button

Use dialogs for:

-   Confirm delete
-   Add account
-   Edit account
-   Add transaction
-   Add recurring commitment
-   Add asset
-   Add credit card
-   Add loan

Do not use dialogs for long complex workflows when a full page is
better.

------------------------------------------------------------------------

# 29. Dropdowns / Popovers

Create reusable Alpine components.

Examples:

``` text
Account selector
Category selector
Date selector
More actions
Filter menu
User menu
```

They should have:

-   Keyboard navigation where practical
-   Escape close
-   Click-away close
-   Proper positioning
-   Consistent shadow
-   Consistent radius

------------------------------------------------------------------------

# 30. Toast Notifications

Add a global toast system.

Use for:

-   Saved transaction
-   Updated account
-   Deleted transaction
-   Reconciled account
-   Saved recurring commitment
-   Validation success

Example:

``` text
✓ Transaction saved

₹863 spent at Thar Retraunt
```

Toasts should not interrupt the user.

------------------------------------------------------------------------

# 31. Empty States

Every list should have a useful empty state.

Example:

``` text
No transactions yet

Your transactions will appear here once you record
your first income, expense or transfer.

[Add transaction]
```

Avoid blank pages.

------------------------------------------------------------------------

# 32. Loading States

Where asynchronous operations exist, use skeletons instead of blank
areas.

Example:

``` text
████████████████
████████
████████████████████
```

Buttons should show loading state:

``` text
Saving...
```

and prevent duplicate submissions.

------------------------------------------------------------------------

# 33. Error States

Validation errors should be directly attached to the relevant input.

Example:

``` text
Amount

[ ₹ ]

Amount is required.
```

Use semantic destructive styling.

Do not rely only on a top-level alert.

------------------------------------------------------------------------

# 34. Responsive Design

The current application is primarily desktop-oriented.

It should become fully responsive.

Breakpoints should cover:

-   Mobile
-   Tablet
-   Desktop
-   Large desktop

Mobile priorities:

1.  Quick Entry
2.  Transactions
3.  Dashboard
4.  Accounts
5.  Upcoming

On mobile:

-   Sidebar becomes drawer
-   Tables become cards or horizontally scrollable where necessary
-   Filters become a sheet
-   Multi-column forms become single column
-   Dashboard cards become stacked
-   Charts resize correctly
-   Primary actions remain accessible

------------------------------------------------------------------------

# 35. Dashboard Redesign

The Dashboard should become the primary polished screen.

Suggested structure:

``` text
September 2026                         [Month] [Quick Entry]

Good evening

Your financial snapshot for this month.

┌────────────┐ ┌────────────┐ ┌────────────┐ ┌────────────┐
│ Received   │ │ Spent      │ │ Credit     │ │ Available  │
│ ₹0         │ │ ₹1,289     │ │ ₹426       │ │ -₹24K      │
└────────────┘ └────────────┘ └────────────┘ └────────────┘

Upcoming
────────────────────────────────────────────
03 Oct   Bike loan                    ₹16,018
03 Oct   Home Rent                    ₹19,700
07 Oct   Land Loan                    ₹39,005
07 Oct   Broadband                     ₹1,178

┌────────────────────────┐ ┌────────────────────────┐
│ Where money went       │ │ Account balances       │
│                        │ │                        │
│     donut chart        │ │ BOB Bank       ₹30K    │
│                        │ │ HDFC           ₹17K    │
└────────────────────────┘ └────────────────────────┘

Recent transactions
────────────────────────────────────────────
...
```

The dashboard should answer immediately:

-   How much came in?
-   How much went out?
-   How much is owed?
-   What is upcoming?
-   Where did money go?
-   What happened recently?

------------------------------------------------------------------------

# 36. Visual Hierarchy

The current screenshots give almost every element similar visual weight.

This must change.

Use a hierarchy:

``` text
Level 1
Page title / primary metric

Level 2
Section title / important financial value

Level 3
Primary action

Level 4
Body information

Level 5
Metadata / helper text
```

Not everything should be bold.

Not everything should be boxed.

Not every section needs a card.

------------------------------------------------------------------------

# 37. Spacing System

Use a consistent spacing scale.

Prefer Tailwind spacing tokens.

Typical:

``` text
4px
8px
12px
16px
20px
24px
32px
40px
48px
```

Default:

-   Card padding: 20--24px
-   Section gap: 24--32px
-   Form field gap: 16px
-   Table row padding: 12--16px
-   Page horizontal padding: 24--32px desktop

Avoid the current inconsistent Bootstrap spacing feel.

------------------------------------------------------------------------

# 38. Border Radius

Use a restrained radius system:

``` text
sm
md
lg
xl
```

Suggested:

-   Inputs: rounded-md
-   Buttons: rounded-md
-   Cards: rounded-xl
-   Badges: rounded-full
-   Dialogs: rounded-xl

Avoid excessive pill-shaped UI except for badges/tags/chips.

------------------------------------------------------------------------

# 39. Shadows

Use shadows sparingly.

Preferred:

``` text
shadow-sm
```

for cards and floating surfaces.

Use stronger shadows only for:

-   Dropdowns
-   Dialogs
-   Command menus
-   Mobile navigation

------------------------------------------------------------------------

# 40. Animation

Keep animation subtle.

Use:

-   100--200ms transitions
-   opacity
-   transform
-   background-color
-   border-color

Avoid:

-   Bouncy animations
-   Large page transitions
-   Excessive motion
-   Decorative animations

Finance software should feel stable.

------------------------------------------------------------------------

# 41. Accessibility

The modernization must improve accessibility.

Requirements:

-   Proper labels
-   Keyboard navigation
-   Visible focus states
-   ARIA where necessary
-   Buttons must have accessible names
-   Icons should not replace accessible text
-   Sufficient contrast
-   Form errors associated with inputs
-   Dialog focus management
-   Do not communicate financial meaning through color alone

Target approximately WCAG AA-level accessibility.

------------------------------------------------------------------------

# 42. Dark Mode

Dark mode is optional for Phase 1.

However, structure the design tokens so dark mode can be introduced
later without rewriting every component.

Do not hard-code colors directly into components where possible.

Use semantic Tailwind classes/tokens.

------------------------------------------------------------------------

# 43. Component Architecture

Create reusable Blade UI primitives.

Suggested structure:

``` text
resources/views/components/ui/

button.blade.php
input.blade.php
textarea.blade.php
select.blade.php
checkbox.blade.php
radio.blade.php
label.blade.php

card.blade.php
card-header.blade.php
card-content.blade.php
card-footer.blade.php

badge.blade.php
alert.blade.php
separator.blade.php

table.blade.php
table-row.blade.php
table-cell.blade.php

dialog.blade.php
dropdown.blade.php
popover.blade.php
tabs.blade.php
sheet.blade.php

toast.blade.php
empty-state.blade.php
skeleton.blade.php

stat-card.blade.php
progress.blade.php
avatar.blade.php
```

Also create domain components where useful:

``` text
resources/views/components/finance/

money.blade.php
account-badge.blade.php
transaction-status.blade.php
category-badge.blade.php
financial-stat.blade.php
account-row.blade.php
loan-progress.blade.php
credit-card-progress.blade.php
```

------------------------------------------------------------------------

# 44. Money Formatting

Create a reusable money display component/helper.

Do not manually format currency differently on every page.

Example:

``` blade
<x-finance.money :amount="$amount" />
```

Requirements:

-   Indian Rupee formatting
-   Correct negative values
-   Optional compact format
-   Consistent decimal behavior
-   Semantic positive/negative styling

Examples:

``` text
₹30,054.27
₹1,289.00
-₹26,79,534.67
```

------------------------------------------------------------------------

# 45. Existing Functionality Must Not Break

This is extremely important.

The agent must NOT:

-   Rewrite backend logic unnecessarily
-   Change financial calculations
-   Change database schema unless explicitly required
-   Change routes unnecessarily
-   Change authorization
-   Change transaction semantics
-   Change credit-card accounting logic
-   Change loan calculations
-   Change reconciliation behavior
-   Change recurring logic

The modernization should be primarily:

``` text
UI
+
UX
+
Component architecture
+
CSS
+
Frontend interactions
```

------------------------------------------------------------------------

# 46. Bootstrap Migration Strategy

Do not remove Bootstrap immediately.

Use this sequence:

## Phase 1

Install/configure Tailwind.

Create the new design system.

## Phase 2

Convert shared layout:

-   App shell
-   Sidebar
-   Header
-   Buttons
-   Cards
-   Typography

## Phase 3

Convert common controls:

-   Inputs
-   Selects
-   Tables
-   Badges
-   Alerts
-   Forms

## Phase 4

Convert pages one by one.

Recommended order:

1.  Dashboard
2.  Quick Entry
3.  Transactions
4.  Accounts
5.  Credit Cards
6.  Loans
7.  Recurring
8.  Assets
9.  Upcoming
10. Reports
11. Reconcile
12. Budgets
13. Settings pages

## Phase 5

Remove unused Bootstrap CSS/classes.

Only remove Bootstrap after all pages have been migrated and verified.

------------------------------------------------------------------------

# 47. Build Configuration

Before making changes, inspect:

``` text
package.json
vite.config.*
tailwind.config.*
resources/css/*
resources/js/*
resources/views/*
routes/*
```

Determine how assets are currently compiled.

Do not blindly replace the build system.

If Tailwind is already present, use the existing setup.

If not, install the current compatible Tailwind setup for the
Laravel/Vite version in the project.

------------------------------------------------------------------------

# 48. JavaScript Strategy

Keep JavaScript lightweight.

Use Alpine.js for UI state.

Avoid building a second application inside Blade.

Recommended:

``` text
Blade
  ↓
Tailwind
  ↓
Alpine.js
  ↓
Backend Laravel routes/controllers
```

Do not introduce React/Vue unless a future requirement genuinely needs
it.

------------------------------------------------------------------------

# 49. Charts

Use Chart.js where existing charts already exist.

Create reusable chart wrappers.

Example:

``` text
resources/js/charts/

spending-chart.js
category-chart.js
cashflow-chart.js
budget-chart.js
net-worth-chart.js
```

Charts should receive server-generated data safely.

Do not hard-code dashboard data into JavaScript.

------------------------------------------------------------------------

# 50. Icon Strategy

Do not mix multiple icon libraries.

Pick Lucide as the standard.

Do not use:

-   Font Awesome for some screens
-   Bootstrap Icons for others
-   Inline random SVGs elsewhere

One icon language should exist throughout the product.

------------------------------------------------------------------------

# 51. Forms and Server Validation

Laravel validation remains the source of truth.

The UI should correctly render:

-   Old input
-   Validation errors
-   Success messages
-   Flash messages
-   Disabled/loading states

Do not move validation logic into JavaScript unnecessarily.

Client-side validation may improve UX but must never replace Laravel
validation.

------------------------------------------------------------------------

# 52. Preserve URLs and Navigation

Existing routes should continue working.

Do not break:

-   Deep links
-   Browser back/forward
-   Form submissions
-   Redirects
-   Pagination
-   Query-string filters

If a page requires UI-only changes, keep the existing controller
contract.

------------------------------------------------------------------------

# 53. Performance

The redesigned UI should not become significantly heavier.

Avoid adding:

-   Huge JS frameworks
-   Large component libraries
-   Multiple duplicate icon packages
-   Multiple CSS frameworks
-   Unnecessary animation libraries

Prefer:

``` text
Tailwind
Alpine
Lucide
Chart.js
```

plus small targeted libraries only where justified.

------------------------------------------------------------------------

# 54. Suggested package direction

The final dependency set should ideally resemble:

``` text
Laravel
Blade
Vite
Tailwind CSS
Alpine.js
Lucide
Chart.js
```

Optional:

``` text
Flatpickr
Floating UI
```

Do not install all optional packages automatically.

Only install them when there is a concrete UI requirement.

------------------------------------------------------------------------

# 55. Implementation Rules for the Agent

Before modifying code:

1.  Inspect the existing project structure.
2.  Identify the current layout Blade file.
3.  Identify shared partials/components.
4.  Identify global CSS.
5.  Identify Bootstrap usage.
6.  Identify JavaScript behavior.
7.  Identify every route/page shown in the screenshots.
8.  Understand which UI elements are server-rendered.
9.  Understand which elements depend on JavaScript.
10. Create a migration plan before mass-editing files.

Do not start by rewriting every Blade file.

------------------------------------------------------------------------

# 56. First Implementation Milestone

The first milestone should only establish the foundation.

Implement:

``` text
Tailwind
Design tokens
Typography
Global layout
Sidebar
Page container
Buttons
Cards
Inputs
Selects
Badges
Tables
Alerts
```

Then migrate the Dashboard.

Once the Dashboard establishes the visual language, use it as the
reference for the rest of the application.

------------------------------------------------------------------------

# 57. Visual QA Checklist

After each page migration, verify:

### Layout

-   [ ] Sidebar aligned
-   [ ] Page header aligned
-   [ ] Content width consistent
-   [ ] Cards aligned
-   [ ] Responsive behavior works

### Typography

-   [ ] Correct hierarchy
-   [ ] No unnecessary bold text
-   [ ] Metadata readable
-   [ ] Numbers easy to scan

### Components

-   [ ] Buttons consistent
-   [ ] Inputs consistent
-   [ ] Badges consistent
-   [ ] Cards consistent
-   [ ] Tables consistent

### Financial UI

-   [ ] Positive values readable
-   [ ] Negative values readable
-   [ ] Debt visually distinct
-   [ ] Planned/unplanned states clear
-   [ ] Currency formatting unchanged

### Interaction

-   [ ] Hover states
-   [ ] Focus states
-   [ ] Loading states
-   [ ] Validation errors
-   [ ] Dialogs
-   [ ] Dropdowns
-   [ ] Toasts

### Responsive

-   [ ] 1440px+
-   [ ] 1024px
-   [ ] 768px
-   [ ] 390px
-   [ ] 360px

------------------------------------------------------------------------

# 58. Screenshot Comparison

Use the supplied screenshots as the **baseline for functionality and
information architecture**, not as the visual target.

The screenshots represent the current application:

-   Dashboard
-   Quick Entry
-   Transactions
-   Accounts
-   Credit Cards
-   Loans
-   Recurring
-   Assets
-   Reports
-   Reconcile

The redesigned application should retain the same information and
capabilities while significantly improving:

``` text
Visual hierarchy
+
Spacing
+
Typography
+
Components
+
Navigation
+
Interaction
+
Responsive behavior
```

Do not remove useful information merely to make the interface look
cleaner.

------------------------------------------------------------------------

# 59. Definition of Done

The modernization is complete when:

-   The application feels like a modern SaaS finance product.
-   Bootstrap visual styling is no longer apparent.
-   All pages share the same design system.
-   Components are reusable.
-   Buttons/forms/cards/tables are consistent.
-   Sidebar is modern and responsive.
-   Quick Entry is significantly easier to use.
-   Dashboard is visually polished.
-   Reports have modern charts.
-   Mobile layout is usable.
-   Accessibility is improved.
-   Existing routes continue to work.
-   Existing financial calculations remain unchanged.
-   No important functionality from the current UI has been removed.
-   No unnecessary frontend framework has been introduced.

------------------------------------------------------------------------

# 60. Important Design Principle

The target is **not**:

> "Make Bootstrap look slightly nicer."

The target is:

> **Build a small, coherent Blade-based design system inspired by the
> principles of shadcn/ui: composable components, semantic tokens,
> restrained styling, excellent defaults, and accessible interactions.**

Laravel + Blade is not a limitation here.

With:

``` text
Tailwind CSS
+
Alpine.js
+
Lucide
+
Blade components
+
Chart.js
```

the application can achieve a very similar level of polish while
remaining a server-rendered Laravel application.

------------------------------------------------------------------------

# 61. Recommended Execution Order

Execute the work in these stages:

``` text
STAGE 1
Audit current frontend
        ↓
STAGE 2
Install/configure Tailwind
        ↓
STAGE 3
Create design tokens
        ↓
STAGE 4
Create Blade UI components
        ↓
STAGE 5
Redesign application shell/sidebar
        ↓
STAGE 6
Redesign Dashboard
        ↓
STAGE 7
Redesign Quick Entry
        ↓
STAGE 8
Redesign Transactions
        ↓
STAGE 9
Redesign Accounts + Credit Cards
        ↓
STAGE 10
Redesign Loans + Recurring + Assets
        ↓
STAGE 11
Redesign Upcoming + Reports
        ↓
STAGE 12
Redesign Reconcile + Budgets + Settings
        ↓
STAGE 13
Responsive QA
        ↓
STAGE 14
Accessibility QA
        ↓
STAGE 15
Remove unused Bootstrap
        ↓
STAGE 16
Final visual regression / functional QA
```

Do not attempt to complete all stages in one uncontrolled rewrite.

Each stage should leave the application working.

------------------------------------------------------------------------

# Final Instruction to the Coding Agent

Treat this document as the **UI modernization specification**.

Before coding, inspect the actual repository and adapt the
implementation to the existing Laravel version, Vite setup, Blade
structure, routes, and JavaScript architecture.

Prefer small reusable components over duplicated markup.

Prefer semantic design tokens over hard-coded values.

Prefer Alpine.js over introducing a SPA framework.

Prefer Tailwind + Blade components over Bootstrap.

Use the supplied screenshots to understand the current information
architecture and preserve all important functionality.

The final result should feel like a **modern, premium, trustworthy
finance application**, not like a Bootstrap admin panel with a new color
scheme.
