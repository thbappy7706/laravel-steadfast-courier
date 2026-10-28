<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Thbappy7706\SteadFastCourier\Events\DeliveryStatusUpdated;
use Thbappy7706\SteadFastCourier\Tests\WebhookDisabledTestCase;

uses(WebhookDisabledTestCase::class);

it('does not register the webhook route when it is disabled', function (): void {
    expect(Route::has('steadfast-courier.webhook'))->toBeFalse();

    Event::fake([DeliveryStatusUpdated::class]);

    $this->withToken('test-bearer-token')
        ->postJson('/steadfast/webhook', steadfastWebhookPayload())
        ->assertNotFound();

    Event::assertNotDispatched(DeliveryStatusUpdated::class);
});
