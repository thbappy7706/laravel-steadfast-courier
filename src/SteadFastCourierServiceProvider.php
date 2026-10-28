<?php

declare(strict_types=1);

namespace Thbappy7706\SteadFastCourier;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Thbappy7706\SteadFastCourier\Contracts\SteadFastClient;
use Thbappy7706\SteadFastCourier\Support\SteadFastConfig;

final class SteadFastCourierServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('steadfast-courier')
            ->hasConfigFile('steadfast-courier');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(SteadFastConfig::class, function (): SteadFastConfig {
            $config = config('steadfast-courier');

            return SteadFastConfig::fromArray(is_array($config) ? $config : []);
        });

        $this->app->singleton(
            SteadFastClient::class,
            fn (): SteadFastCourier => new SteadFastCourier($this->app->make(SteadFastConfig::class)),
        );

        $this->app->alias(SteadFastClient::class, SteadFastCourier::class);
    }

    public function packageBooted(): void
    {
        if (config('steadfast-courier.webhook.enabled') !== true) {
            return;
        }

        $this->loadRoutesFrom(__DIR__.'/../routes/webhook.php');
    }
}
