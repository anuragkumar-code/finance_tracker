<?php

namespace App\Models;

use App\Enums\SettlementDirection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One squaring-up of shared cost with one person.
 *
 * Written and removed only by SharedExpenseService, which owns the entries it
 * creates and the reductions it makes — that is what lets an undo put every
 * row back exactly as it was.
 */
class SharedSettlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'person_id',
        'direction',
        'amount',
        'settled_on',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'direction' => SettlementDirection::class,
            'amount' => 'decimal:2',
            'settled_on' => 'date',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /** The expense rows this settlement reduced, and by how much. */
    public function allocations(): HasMany
    {
        return $this->hasMany(SharedSettlementAllocation::class);
    }

    /** The ledger entries this settlement wrote. */
    public function entries(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
