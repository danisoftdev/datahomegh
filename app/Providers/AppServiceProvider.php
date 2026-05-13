<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Contract\Messaging as MessagingContract;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Mockery;

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
        if ($this->app->environment('testing')) {
            $mock = Mockery::mock(MessagingContract::class);
            $mock->shouldReceive('sendMulticast')->andReturn(MulticastSendReport::withItems([]));
            $this->app->instance(MessagingContract::class, $mock);
        }
    }
}
