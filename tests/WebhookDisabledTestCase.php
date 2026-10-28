<?php

declare(strict_types=1);

namespace Thbappy7706\SteadFastCourier\Tests;

abstract class WebhookDisabledTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('steadfast-courier.webhook.enabled', false);
    }
}
