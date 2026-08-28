<?php

namespace App\Providers;

use App\Services\Push\FcmHttpV1Sender;
use App\Services\Push\LogPushSender;
use App\Services\Push\PushSender;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PushSender::class, function () {
            return config('services.fcm.enabled')
                ? new FcmHttpV1Sender
                : new LogPushSender;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
