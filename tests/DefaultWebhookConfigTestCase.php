<?php

declare(strict_types=1);

namespace Thbappy7706\SteadFastCourier\Tests;

abstract class DefaultWebhookConfigTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('steadfast-courier.webhook.path', '   ');
        $app['config']->set('steadfast-courier.webhook.middleware', ['', null]);
    }
}
