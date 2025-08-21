<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Ia\IaService;
use App\Services\Ia\MistralAgentService;

class IaServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(IaService::class, function ($app) {
            return new IaService($app->make(MistralAgentService::class));
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
