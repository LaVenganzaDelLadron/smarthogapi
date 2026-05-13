<?php

namespace App\Providers;

use App\Models\Hogs;
use App\Observers\HogsObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Hogs::observe(HogsObserver::class);
    }
}
