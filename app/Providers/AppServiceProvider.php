<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(\App\Payments\PaymentGateway::class, function () {
            return match (config('services.pg.driver')) {
                // 'inicis' => new \App\Payments\InicisGateway(...), // 향후 분리 태스크
                default => new \App\Payments\FakeGateway(),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\Event::listen(function (\SocialiteProviders\Manager\SocialiteWasCalled $event) {
            $event->extendSocialite('kakao', \SocialiteProviders\Kakao\KakaoProvider::class);
        });
    }
}
