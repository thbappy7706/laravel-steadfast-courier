<?php

declare(strict_types=1);

use Thbappy7706\SteadFastCourier\Enums\DeliveryType;

it('has the expected backing values', function (): void {
    expect(DeliveryType::HomeDelivery->value)->toBe(0)
        ->and(DeliveryType::PointDelivery->value)->toBe(1)
        ->and(DeliveryType::cases())->toHaveCount(2);
});

it('provides a human readable label', function (DeliveryType $type, string $label): void {
    expect($type->label())->toBe($label);
})->with([
    'home delivery' => [DeliveryType::HomeDelivery, 'Home delivery'],
    'point delivery' => [DeliveryType::PointDelivery, 'Point delivery'],
]);
