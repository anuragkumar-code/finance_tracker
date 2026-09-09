<?php

namespace App\Enums;

/**
 * How the household buys from a merchant.
 *
 * Category says WHAT was bought; channel says HOW. Blinkit groceries and a
 * supermarket run are both "Food", but one is a ten-minute delivery habit and
 * the other is a weekly shop — and that distinction is where quick-commerce
 * spending quietly accumulates.
 */
enum MerchantChannel: string
{
    case QuickCommerce = 'quick_commerce';
    case Ecommerce = 'ecommerce';
    case FoodDelivery = 'food_delivery';
    case Offline = 'offline';
    case Subscription = 'subscription';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::QuickCommerce => 'Quick commerce',
            self::Ecommerce => 'Online shopping',
            self::FoodDelivery => 'Food delivery',
            self::Offline => 'In person',
            self::Subscription => 'Subscription',
            self::Other => 'Other',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::QuickCommerce => 'Blinkit, Zepto, Instamart — minutes',
            self::Ecommerce => 'Amazon, Flipkart, Myntra — days',
            self::FoodDelivery => 'Swiggy, Zomato',
            self::Offline => 'Shops, restaurants, cash',
            self::Subscription => 'Recurring services',
            self::Other => 'Anything else',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::QuickCommerce => 'danger',
            self::Ecommerce => 'primary',
            self::FoodDelivery => 'warning',
            self::Offline => 'secondary',
            self::Subscription => 'info',
            self::Other => 'light',
        };
    }

    /**
     * Best guess from a merchant's name, so the household does not have to
     * classify every shop by hand. Only ever fills a blank — a channel set
     * deliberately in Settings is never overwritten.
     *
     * Matching is on whole words where the name is short and ambiguous, to
     * avoid a shop called "Zomato Kitchen Supplies" being read as food delivery.
     */
    public static function guessFrom(string $merchantName): self
    {
        $name = strtolower(trim($merchantName));

        $map = [
            self::QuickCommerce->value => [
                'blinkit', 'zepto', 'instamart', 'swiggy instamart', 'dunzo',
                'bigbasket now', 'bb now', 'amazon now', 'flipkart minutes',
                'zomato instant', 'jiomart express',
            ],
            self::Ecommerce->value => [
                'amazon', 'flipkart', 'myntra', 'ajio', 'meesho', 'nykaa',
                'tata cliq', 'snapdeal', 'firstcry', 'pepperfry', 'croma',
                'reliance digital', 'jiomart', 'bigbasket',
            ],
            self::FoodDelivery->value => [
                'swiggy', 'zomato', 'eatsure', 'dominos', 'pizza hut', 'box8',
                'faasos', 'behrouz', 'ovenstory', 'magicpin',
            ],
            self::Subscription->value => [
                'netflix', 'prime video', 'hotstar', 'spotify', 'youtube premium',
                'sony liv', 'zee5', 'apple', 'google one', 'icloud',
            ],
        ];

        foreach ($map as $channel => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($name, $needle)) {
                    return self::from($channel);
                }
            }
        }

        return self::Offline;
    }
}
