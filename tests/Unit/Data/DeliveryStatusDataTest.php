<?php

declare(strict_types=1);

use Thbappy7706\SteadFastCourier\Data\DeliveryStatusData;
use Thbappy7706\SteadFastCourier\Enums\DeliveryStatus;

it('reads the delivery_status field', function (): void {
    $data = DeliveryStatusData::fromArray(['delivery_status' => 'delivered']);

    expect($data->status)->toBe(DeliveryStatus::Delivered);
});

it('falls back to the status field', function (): void {
    $data = DeliveryStatusData::fromArray(['status' => 'cancelled']);

    expect($data->status)->toBe(DeliveryStatus::Cancelled);
});

it('prefers delivery_status over status', function (): void {
    $data = DeliveryStatusData::fromArray([
        'delivery_status' => 'delivered',
        'status' => 'pending',
    ]);

    expect($data->status)->toBe(DeliveryStatus::Delivered);
});

it('maps unknown and missing statuses to unknown', function (array $payload): void {
    expect(DeliveryStatusData::fromArray($payload)->status)->toBe(DeliveryStatus::Unknown);
})->with([
    'empty' => [[]],
    'unknown value' => [['delivery_status' => 'lost']],
    'non-string value' => [['delivery_status' => 123]],
    'null value' => [['delivery_status' => null]],
]);

it('converts the status to an array', function (): void {
    $data = new DeliveryStatusData(DeliveryStatus::PartialDelivered);

    expect($data->toArray())->toBe(['delivery_status' => 'partial_delivered']);
});
