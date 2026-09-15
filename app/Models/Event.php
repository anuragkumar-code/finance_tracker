<?php

namespace App\Models;

use App\Enums\EventKind;
use App\Enums\TransactionType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A trip or an occasion whose cost is worth seeing as one figure.
 *
 * An event is a label across categories, not a category itself: the Alleppey
 * houseboat, the dinners and the cab to the station all belong to the trip,
 * whatever they are filed under.
 */
class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'kind',
        'start_date',
        'end_date',
        'notes',
        'is_archived',
    ];

    protected function casts(): array
    {
        return [
            'kind' => EventKind::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'is_archived' => 'boolean',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** Spending entries that belong to this event. */
    public function expenses(): HasMany
    {
        return $this->transactions()->where('type', TransactionType::Expense->value);
    }

    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'event_person');
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(SharedSettlement::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('is_archived', false);
    }

    /** Newest first — the trip you are on, or just back from, leads. */
    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('start_date')->orderByDesc('id');
    }

    /** Whether a date falls inside the event (a one-day event ends when it starts). */
    public function covers(CarbonInterface $date): bool
    {
        $end = $this->end_date ?? $this->start_date;

        return $date->betweenIncluded($this->start_date->copy()->startOfDay(), $end->copy()->endOfDay());
    }

    /** "12–14 Sep 2026", collapsing the parts that repeat. */
    public function dateRange(): string
    {
        $start = $this->start_date;
        $end = $this->end_date;

        if ($end === null || $end->isSameDay($start)) {
            return $start->format('d M Y');
        }

        if ($start->isSameMonth($end)) {
            return $start->format('d').'–'.$end->format('d M Y');
        }

        return $start->isSameYear($end)
            ? $start->format('d M').' – '.$end->format('d M Y')
            : $start->format('d M Y').' – '.$end->format('d M Y');
    }

    public function isTrip(): bool
    {
        return $this->kind === EventKind::Trip;
    }
}
