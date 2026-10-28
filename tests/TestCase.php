<?php

declare(strict_types=1);

namespace Thbappy7706\SteadFastCourier\Tests;

use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;
use Thbappy7706\SteadFastCourier\SteadFastCourierServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            SteadFastCourierServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('steadfast-courier', [
            'base_url' => 'https://portal.steadfast.com.bd/api/v1',
            'api_key' => 'test-api-key',
            'secret_key' => 'test-secret-key',
            'webhook_bearer_token' => 'test-bearer-token',
            'webhook' => [
                'enabled' => true,
                'path' => 'steadfast/webhook',
                'middleware' => [],
            ],
            'timeout' => 15,
            'connect_timeout' => 5,
            'retry' => [
                'times' => 2,
                'sleep_ms' => 0,
            ],
            'bulk_chunk_size' => 500,
            'logging' => [
                'enabled' => false,
                'channel' => null,
            ],
        ]);
    }
}
