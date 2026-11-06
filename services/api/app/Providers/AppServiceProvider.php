<?php

namespace App\Providers;

use App\Services\PaymentProcessorRegistry;
use App\Services\Payments\StripePaymentProcessor;
use App\Services\Payments\PayPalPaymentProcessor;
use App\Services\Payments\FlutterwavePaymentProcessor;
use App\Services\Payments\PaystackPaymentProcessor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;

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
        View::composer(['components.layouts.workspace', 'components.layouts.public'], function (ViewInstance $view): void {
            $avatarUrl = null;

            if (auth()->check()) {
                $profileImagePath = DB::table('people')
                    ->where('user_id', auth()->id())
                    ->value('profile_image_path');

                if (is_string($profileImagePath) && $profileImagePath !== '' && Storage::disk('public')->exists($profileImagePath)) {
                    $avatarUrl = route('public.storage', ['path' => $profileImagePath]);
                }
            }

            $view->with('authenticatedPersonAvatarUrl', $avatarUrl);
        });
    }
}
