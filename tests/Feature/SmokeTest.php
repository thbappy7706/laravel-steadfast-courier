<?php

declare(strict_types=1);

use Thbappy7706\SteadFastCourier\Support\SteadFastConfig;
use Thbappy7706\SteadFastCourier\Tests\TestCase;

uses(TestCase::class);

it('boots the test application with the package configuration', function (): void {
    $config = app(SteadFastConfig::class);

    expect($config->apiKey)->toBe('test-api-key')
        ->and($config->baseUrl)->toBe('https://portal.steadfast.com.bd/api/v1');
});
