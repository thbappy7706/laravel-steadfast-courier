<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Thbappy7706\SteadFastCourier\Data\WebhookPayloadData;
use Thbappy7706\SteadFastCourier\Enums\DeliveryStatus;
use Thbappy7706\SteadFastCourier\Events\DeliveryStatusUpdated;

it('exposes the webhook payload on the event', function (): void {
    $payload = new WebhookPayloadData(
        consignmentId: 1424107,
        invoice: 'INV-1001',
        status: DeliveryStatus::Delivered,
        codAmount: 1000.75,
        updatedAt: CarbonImmutable::parse('2026-10-28T12:00:00+06:00'),
    );

    $event = new DeliveryStatusUpdated($payload);

    expect($event->payload)->toBe($payload);
});
