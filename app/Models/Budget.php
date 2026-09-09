<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A monthly spending target for one category.
 *
 * Dated, so raising a budget later never rewrites how an earlier month was
 * judged — a report on February should reflect what February was actually
 * measured against.
 */
class Budget extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'amount',
        'effective_from',
        'effective_to',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function isCurrent(): bool
    {
        return $this->effective_to === null;
    }

    /** Budgets in force for the month containing the given date. */
    public function scopeCoveringMonth(Builder $query, CarbonInterface $month): Builder
    {
        $monthStart = $month->copy()->startOfMonth()->toDateString();

        return $query
            ->where('effective_from', '<=', $monthStart)
            ->where(function (Builder $q) use ($monthStart) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $monthStart);
            });
    }
}
