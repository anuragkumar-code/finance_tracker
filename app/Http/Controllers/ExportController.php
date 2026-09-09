<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Getting your data out (spec section 31).
 *
 * This is a private local app, so the export exists for backup and for opening
 * the ledger in a spreadsheet — not for any external service. Rows are streamed
 * so a long history never exhausts memory.
 *
 * Voided entries are included, marked as such, because an export that silently
 * drops them would not reconcile against the app it came from.
 */
class ExportController extends Controller
{
    public function transactions(Request $request): StreamedResponse
    {
        $filename = 'transactions-'.now()->format('Y-m-d').'.csv';

        $query = Transaction::withTrashed()
            ->with(['account', 'category', 'subcategory', 'payer', 'beneficiary', 'merchant'])
            ->when($request->filled('start'), fn ($q) => $q->where('transaction_date', '>=', $request->date('start')))
            ->when($request->filled('end'), fn ($q) => $q->where('transaction_date', '<=', $request->date('end')))
            ->orderBy('transaction_date')
            ->orderBy('id');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');

            // BOM so Excel reads the rupee sign and names correctly.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Date', 'Type', 'Amount', 'Direction', 'Account', 'Category', 'Subcategory',
                'Paid by', 'For', 'Merchant', 'Planned', 'Purpose', 'Description', 'Notes',
                'Reference', 'Source', 'Voided', 'Void reason', 'Recorded at',
            ]);

            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $t) {
                    fputcsv($out, [
                        $t->transaction_date->toDateString(),
                        $t->type->value,
                        $t->amount,
                        $t->balance_effect->value,
                        $t->account?->name,
                        $t->category?->name,
                        $t->subcategory?->name,
                        $t->payer?->name,
                        $t->beneficiary?->name,
                        $t->merchant?->name,
                        $t->planned_status?->value,
                        $t->purpose?->value,
                        $t->description,
                        $t->notes,
                        $t->reference,
                        $t->source,
                        $t->trashed() ? 'yes' : 'no',
                        $t->void_reason,
                        $t->created_at?->toDateTimeString(),
                    ]);
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** Current balances, for a quick snapshot alongside the ledger. */
    public function accounts(): StreamedResponse
    {
        $filename = 'accounts-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Account', 'Type', 'Owner', 'Institution', 'Opening balance',
                'Opening date', 'Current balance', 'Set aside', 'Active',
            ]);

            foreach (\App\Models\Account::with('owner')->orderBy('type')->orderBy('name')->get() as $a) {
                fputcsv($out, [
                    $a->name,
                    $a->type->value,
                    $a->owner?->name,
                    $a->institution,
                    $a->opening_balance,
                    $a->opening_balance_date->toDateString(),
                    $a->cached_balance,
                    $a->is_set_aside ? 'yes' : 'no',
                    $a->is_active ? 'yes' : 'no',
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
