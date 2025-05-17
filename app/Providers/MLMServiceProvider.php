<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\MLMNotificationService;
use App\Services\MLMPointService;

class MLMServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(MLMNotificationService::class, function ($app) {
            return new MLMNotificationService();
        });

        $this->app->singleton(MLMPointService::class, function ($app) {
            return new MLMPointService($app->make(MLMNotificationService::class));
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