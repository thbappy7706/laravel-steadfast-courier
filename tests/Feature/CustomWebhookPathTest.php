<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Thbappy7706\SteadFastCourier\Events\DeliveryStatusUpdated;
use Thbappy7706\SteadFastCourier\Http\Middleware\VerifyWebhookToken;
use Thbappy7706\SteadFastCourier\Tests\CustomWebhookPathTestCase;

uses(CustomWebhookPathTestCase::class);

it('registers the webhook route at the configured path', function (): void {
    $route = app('router')->getRoutes()->getByName('steadfast-courier.webhook');

    expect($route)->not->toBeNull()
        ->and($route->uri())->toBe('custom/hooks/steadfast')
        ->and(route('steadfast-courier.webhook'))->toEndWith('/custom/hooks/steadfast')
        ->and($route->gatherMiddleware())->toBe([VerifyWebhookToken::class]);
});

it('accepts webhooks at the configured path', function (): void {
    Event::fake([DeliveryStatusUpdated::class]);

    $this->withToken('test-bearer-token')
        ->postJson(route('steadfast-courier.webhook'), steadfastWebhookPayload())
        ->assertOk();

    Event::assertDispatched(DeliveryStatusUpdated::class);
});
