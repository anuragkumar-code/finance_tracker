<?php

namespace App\Providers;

use App\Support\Money;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // @inr($amount) -> ₹1,35,000.00 ; @inrc($amount) -> ₹1.35L
        Blade::directive('inr', fn (string $expression) => "<?php echo \\App\\Support\\Money::inr({$expression}); ?>");
        Blade::directive('inrc', fn (string $expression) => "<?php echo \\App\\Support\\Money::compact({$expression}); ?>");

        // The UI is Bootstrap 5, not Laravel's default Tailwind pagination markup.
        Paginator::useBootstrapFive();
    }
}
