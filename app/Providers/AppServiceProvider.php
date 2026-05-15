<?php

namespace App\Providers;

use App\Models\Hogs;
use App\Models\User;
use App\Observers\HogsObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
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
        Gate::define('access-owned-model', function (User $user, Model $model): bool {
            return method_exists($model, 'belongsToUser') && $model->belongsToUser($user->id);
        });

        Hogs::observe(HogsObserver::class);
    }
}
