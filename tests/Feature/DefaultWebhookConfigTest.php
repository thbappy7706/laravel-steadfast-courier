<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Thbappy7706\SteadFastCourier\Events\DeliveryStatusUpdated;
use Thbappy7706\SteadFastCourier\Http\Middleware\VerifyWebhookToken;
use Thbappy7706\SteadFastCourier\Tests\DefaultWebhookConfigTestCase;

uses(DefaultWebhookConfigTestCase::class);

it('falls back to the default path and ignores invalid middleware entries', function (): void {
    $route = app('router')->getRoutes()->getByName('steadfast-courier.webhook');

    expect($route)->not->toBeNull()
        ->and($route->uri())->toBe('steadfast/webhook')
        ->and($route->gatherMiddleware())->toBe([VerifyWebhookToken::class]);
});

it('accepts webhooks on the fallback path', function (): void {
    Event::fake([DeliveryStatusUpdated::class]);

    $this->withToken('test-bearer-token')
        ->postJson(route('steadfast-courier.webhook'), steadfastWebhookPayload())
        ->assertOk();

    Event::assertDispatched(DeliveryStatusUpdated::class);
});
