<?php

namespace App\Models;

use App\Enums\MerchantChannel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Merchant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'channel',
        'default_category_id',
        'default_subcategory_id',
        'default_account_id',
        'default_payer_id',
        'default_beneficiary_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'channel' => MerchantChannel::class,
        ];
    }

    public function defaultCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'default_category_id');
    }

    public function defaultSubcategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'default_subcategory_id');
    }

    public function defaultAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_account_id');
    }

    public function defaultPayer(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'default_payer_id');
    }

    public function defaultBeneficiary(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'default_beneficiary_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * The remembered defaults for quick entry (spec section 16), as a sparse
     * array ready to merge over a new transaction's attributes.
     */
    public function quickEntryDefaults(): array
    {
        return array_filter([
            'category_id' => $this->default_category_id,
            'subcategory_id' => $this->default_subcategory_id,
            'account_id' => $this->default_account_id,
            'payer_id' => $this->default_payer_id,
            'beneficiary_id' => $this->default_beneficiary_id,
        ], fn ($value) => $value !== null);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
    /**
     * Fill in a channel from the merchant's name when none was chosen.
     *
     * Only ever fills a blank the household left: a channel set deliberately in
     * Settings is never second-guessed by pattern matching.
     */
    public function guessChannelIfUnset(): void
    {
        if ($this->channel === null || $this->channel === MerchantChannel::Offline) {
            $guess = MerchantChannel::guessFrom($this->name);

            if ($guess !== MerchantChannel::Offline) {
                $this->channel = $guess;
                $this->save();
            }
        }
    }

    public function scopeOfChannel(Builder $query, MerchantChannel $channel): Builder
    {
        return $query->where('channel', $channel->value);
    }
}