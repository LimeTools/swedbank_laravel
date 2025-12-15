<?php

namespace Swedbank\LaravelPaymentApi;

use Illuminate\Support\ServiceProvider;

class SwedbankPaymentServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/swedbank.php',
            'swedbank'
        );

        $this->app->singleton(SwedbankPaymentApi::class, function ($app) {
            $isSandbox = config('swedbank.environment') === 'sandbox';
            return new SwedbankPaymentApi($isSandbox);
        });

        // Register alias for facade
        $this->app->alias(SwedbankPaymentApi::class, 'swedbank.payment');
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/swedbank.php' => config_path('swedbank.php'),
        ], 'swedbank-config');
    }
}

