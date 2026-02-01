<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Prenda;
use App\Observers\PrendaObserver;

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
        // Registrar Observer para Prenda
        Prenda::observe(PrendaObserver::class);
    }
}
