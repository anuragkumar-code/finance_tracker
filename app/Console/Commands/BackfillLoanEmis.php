<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\LoanPayment;
use App\Models\Transaction;
use App\Services\LoanService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Give the missing ledger entries to EMIs already recorded as paid.
 *
 * When a loan is created, instalments that already fell due are marked paid as
 * history and never get an entry. The result is that the largest regular
 * outflow in the household — a ₹39,005 EMI — does not appear in spending at
 * all, while a second loan's EMI, confirmed through the app, does. This closes
 * that gap.
 *
 * Reports what it would do and writes nothing without --apply. Where an entry
 * for the EMI already exists it is linked rather than written again, so the
 * command can never double-count a payment.
 */
class BackfillLoanEmis extends Command
{
    protected $signature = 'loans:backfill-emis
        {--map=* : Which account paid a loan, as "Loan name=Account name". Repeatable.}
        {--from= : Only instalments paid on or after this date (YYYY-MM-DD).}
        {--apply : Write the entries. Without this the command only reports.}';

    protected $description = 'Write the ledger entries missing from EMIs already marked paid';

    public function handle(LoanService $loans): int
    {
        $mapping = $this->accountMapping();

        if ($mapping === null) {
            return self::FAILURE;
        }

        $instalments = LoanPayment::query()
            ->where('status', 'paid')
            ->whereNull('transaction_id')
            ->when($this->option('from'), fn ($q, $from) => $q->whereDate('payment_date', '>=', $from))
            ->with('loan')
            ->orderBy('due_date')
            ->get();

        if ($instalments->isEmpty()) {
            $this->info('Every paid instalment already has a ledger entry. Nothing to do.');

            return self::SUCCESS;
        }

        $apply = (bool) $this->option('apply');
        $rows = [];
        $plan = [];

        foreach ($instalments as $instalment) {
            $loanName = $instalment->loan->name;
            $date = ($instalment->payment_date ?? $instalment->due_date);

            $existing = $this->existingEntryFor($instalment);
            $account = $existing?->account ?? ($mapping[$loanName] ?? null);

            if ($account === null) {
                $rows[] = [$loanName, $instalment->period_number, $date->format('d M Y'),
                    (string) $instalment->amount, '—', 'skipped — no account given'];

                continue;
            }

            $action = $existing !== null ? 'link to entry #'.$existing->id : 'write entry';

            $rows[] = [$loanName, $instalment->period_number, $date->format('d M Y'),
                (string) $instalment->amount, $account->name, $apply ? $action : 'would '.$action];

            $plan[] = ['instalment' => $instalment, 'account' => $account, 'existing' => $existing];
        }

        $this->newLine();
        $this->table(['Loan', 'No.', 'Dated', 'Amount', 'Paid from', 'Action'], $rows);

        $toWrite = collect($plan)->whereNull('existing')->count();
        $toLink = collect($plan)->whereNotNull('existing')->count();
        $total = collect($plan)->whereNull('existing')
            ->reduce(fn ($c, $p) => bcadd($c, (string) $p['instalment']->amount, 2), '0.00');

        $this->newLine();
        $this->line(sprintf(
            '%d instalment(s) examined · %d entries to write (₹%s) · %d existing entries to link · %d skipped.',
            $instalments->count(), $toWrite, number_format((float) $total, 2), $toLink,
            $instalments->count() - count($plan),
        ));

        if (! $apply) {
            $this->newLine();
            $this->warn('Dry run — nothing was written. Re-run with --apply to record these.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($plan, $loans) {
            foreach ($plan as $step) {
                $step['existing'] !== null
                    ? $loans->linkExistingEntry($step['instalment'], $step['existing'])
                    : $loans->recordMissingEntry($step['instalment'], $step['account']);
            }
        });

        $this->newLine();
        $this->info(sprintf('%d instalment(s) now have a ledger entry.', count($plan)));

        return self::SUCCESS;
    }

    /**
     * An EMI entry someone typed in by hand before the instalment was linked.
     *
     * Matched on the loan's own description format, the exact amount and a date
     * near the instalment — close enough to be the same payment, specific
     * enough not to catch an unrelated spend of the same size.
     *
     * @return Transaction|null
     */
    private function existingEntryFor(LoanPayment $instalment): ?Transaction
    {
        $date = ($instalment->payment_date ?? $instalment->due_date);

        return Transaction::query()
            ->where('type', 'expense')
            ->whereNull('loan_payment_id')
            ->where('amount', $instalment->amount)
            ->whereBetween('transaction_date', [
                $date->copy()->subDays(10)->toDateString(),
                $date->copy()->addDays(10)->toDateString(),
            ])
            ->where('description', 'like', '%'.$instalment->loan->name.'%EMI%')
            ->with('account')
            ->first();
    }

    /** @return array<string, Account>|null */
    private function accountMapping(): ?array
    {
        $mapping = [];

        foreach ($this->option('map') as $pair) {
            if (! str_contains($pair, '=')) {
                $this->error("--map must look like \"Land Loan=HDFC\", got \"{$pair}\".");

                return null;
            }

            [$loanName, $accountName] = array_map('trim', explode('=', $pair, 2));

            $account = Account::query()->own()->where('name', $accountName)->first();

            if ($account === null) {
                $this->error("No account named \"{$accountName}\".");

                return null;
            }

            if ($account->isLiability()) {
                $this->error("\"{$accountName}\" is a card. An EMI is paid from a bank or cash account.");

                return null;
            }

            $mapping[$loanName] = $account;
        }

        return $mapping;
    }
}
