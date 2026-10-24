<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\AgendaExterna;
use App\Services\Agendamento\AgendaExternaVazia;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
       $this->app->bind(AgendaExterna::class, AgendaExternaVazia::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
