<?php

namespace App\Models;

use App\Enums\MerchantChannel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A family of merchants — Cabs, E-commerce, Food delivery.
 *
 * This is the master behind the merchant picker. It exists so that choosing
 * where you bought something is a two-step scan (which kind of place, then
 * which one) rather than hunting through one long alphabetical list.
 *
 * It is not a channel and not a category. See the migration for why those
 * three are kept apart.
 */
class MerchantGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'default_channel',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'default_channel' => MerchantChannel::class,
            'sort_order' => 'integer',
        ];
    }

    public function merchants(): HasMany
    {
        return $this->hasMany(Merchant::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Hand-ordered first, then alphabetical inside the same rank. */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Which group a merchant name belongs to, by name.
     *
     * Used to file the merchants that already existed before groups did, and to
     * pre-select a group when a new name is typed into quick entry. It only
     * ever proposes — nothing here overwrites a group a person chose by hand.
     *
     * Matching is on the longest needle first, so "amazon now" is read as
     * quick commerce rather than being caught by the plain "amazon" rule.
     *
     * @return string|null the group name, or null when nothing matches
     */
    public static function guessNameFor(string $merchantName): ?string
    {
        $name = strtolower(trim($merchantName));

        if ($name === '') {
            return null;
        }

        $rules = [
            'Cabs & rides' => [
                'uber', 'ola', 'rapido', 'namma yatri', 'blusmart', 'meru',
                'auto', 'cab', 'taxi',
            ],
            'E-commerce' => [
                'amazon now', 'amazon', 'flipkart minutes', 'flipkart', 'blinkit',
                'zepto', 'instamart', 'bigbasket', 'jiomart', 'myntra', 'ajio',
                'meesho', 'nykaa', 'tata cliq', 'snapdeal', 'firstcry',
                'pepperfry', 'croma', 'dunzo', 'reliance digital',
            ],
            'Food delivery' => [
                'zomato', 'swiggy', 'eatsure', 'dominos', "domino's", 'pizza hut',
                'box8', 'faasos', 'behrouz', 'ovenstory', 'magicpin', 'toing',
                'kfc delivery',
            ],
            'Restaurants & cafés' => [
                'restaurant', 'retraunt', 'resturant', 'cafe', 'café', 'dhaba',
                'bakery', 'biryani', 'darshini', 'tiffin', 'canteen', 'barbeque',
                'hotel ', 'kitchen',
            ],
            'Groceries & kirana' => [
                'kirana', 'grocery', 'groceries', 'supermarket', 'super market',
                'more retail', 'dmart', 'd mart', 'reliance fresh', 'vegetable',
                'milk', 'nandini', 'heritage',
            ],
            'Fuel & transport' => [
                'petrol', 'diesel', 'fuel', 'hp ', 'bharat petroleum', 'indian oil',
                'shell', 'fastag', 'metro', 'bmtc', 'bus', 'irctc', 'railway',
                'parking', 'toll',
            ],
            'Bills & insurance' => [
                'pension', 'insurance', 'lic', 'premium', 'broadband', 'airtel',
                'jio', 'vodafone', 'vi ', 'bescom', 'electricity', 'water bill',
                'gas', 'recharge', 'postpaid', 'policy',
            ],
            'Subscriptions' => [
                'netflix', 'prime video', 'hotstar', 'spotify', 'youtube premium',
                'sony liv', 'sonyliv', 'zee5', 'google one', 'icloud', 'apple one',
                'subscription',
            ],
            'Health & pharmacy' => [
                'pharma', 'pharmacy', 'apollo', 'medplus', '1mg', 'pharmeasy',
                'practo', 'hospital', 'clinic', 'dental', 'clove', 'doctor',
                'lab', 'diagnostic', 'medicine',
            ],
            'Beauty & grooming' => [
                'yes madam', 'urban company', 'urbanclap', 'salon', 'spa',
                'lakme', 'naturals', 'barber', 'grooming', 'beauty',
            ],
            'Travel & stays' => [
                'makemytrip', 'goibibo', 'cleartrip', 'yatra', 'oyo', 'airbnb',
                'indigo', 'vistara', 'air india', 'booking.com', 'trip', 'resort',
            ],
            'Home & services' => [
                'bazar', 'bazaar', 'hardware', 'electrician', 'plumber',
                'carpenter', 'furniture', 'paint', 'repair', 'laundry', 'dry clean',
                'maid', 'cook', 'stationery', 'xerox', 'print',
            ],
            'Education' => [
                'school', 'college', 'tuition', 'course', 'udemy', 'coursera',
                'byju', 'unacademy', 'exam', 'fees',
            ],
        ];

        // Longest needle wins, so a specific name beats a generic one no matter
        // which group happens to be listed first.
        $best = null;
        $bestLength = 0;

        foreach ($rules as $group => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($name, $needle) && strlen($needle) > $bestLength) {
                    $best = $group;
                    $bestLength = strlen($needle);
                }
            }
        }

        return $best;
    }
}
