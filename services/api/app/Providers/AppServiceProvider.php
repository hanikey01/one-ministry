<?php

namespace App\Providers;

use App\Services\PaymentProcessorRegistry;
use App\Services\Payments\StripePaymentProcessor;
use App\Services\Payments\PayPalPaymentProcessor;
use App\Services\Payments\FlutterwavePaymentProcessor;
use App\Services\Payments\PaystackPaymentProcessor;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PaymentProcessorRegistry::class, fn () => new PaymentProcessorRegistry([
            $this->app->make(StripePaymentProcessor::class),
            $this->app->make(PayPalPaymentProcessor::class),
            $this->app->make(FlutterwavePaymentProcessor::class),
            $this->app->make(PaystackPaymentProcessor::class),
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
