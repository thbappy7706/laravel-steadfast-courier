<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Thbappy7706\SteadFastCourier\Data\WebhookPayloadData;
use Thbappy7706\SteadFastCourier\Enums\DeliveryStatus;
use Thbappy7706\SteadFastCourier\Exceptions\InvalidWebhookException;

it('maps a complete webhook payload', function (): void {
    $payload = WebhookPayloadData::fromArray(steadfastWebhookPayload());

    expect($payload->consignmentId)->toBe(1424107)
        ->and($payload->invoice)->toBe('INV-1001')
        ->and($payload->status)->toBe(DeliveryStatus::Delivered)
        ->and($payload->codAmount)->toBe(1000.75)
        ->and($payload->updatedAt)->toBeInstanceOf(CarbonImmutable::class)
        ->and($payload->updatedAt->toIso8601String())->toBe('2026-10-28T12:00:00+06:00')
        ->and($payload->notificationType)->toBe('delivery_status');
});

it('accepts a string consignment id and a numeric string amount', function (): void {
    $payload = WebhookPayloadData::fromArray(steadfastWebhookPayload([
        'consignment_id' => '1424107',
        'cod_amount' => '1000.75',
    ]));

    expect($payload->consignmentId)->toBe('1424107')
        ->and($payload->codAmount)->toBe(1000.75);
});

it('maps an unrecognised status to unknown', function (): void {
    $payload = WebhookPayloadData::fromArray(steadfastWebhookPayload(['status' => 'package_lost']));

    expect($payload->status)->toBe(DeliveryStatus::Unknown);
});

it('normalises a non-string notification type to null', function (): void {
    $payload = WebhookPayloadData::fromArray(steadfastWebhookPayload([
        'notification_type' => ['delivery_status'],
    ]));

    expect($payload->notificationType)->toBeNull();
});

it('defaults a missing notification type to null', function (): void {
    $data = steadfastWebhookPayload();
    unset($data['notification_type']);

    expect(WebhookPayloadData::fromArray($data)->notificationType)->toBeNull();
});

it('rejects an invalid consignment id', function (mixed $value): void {
    $data = steadfastWebhookPayload();
    $data['consignment_id'] = $value;

    expect(fn () => WebhookPayloadData::fromArray($data))
        ->toThrow(
            InvalidWebhookException::class,
            'The webhook payload does not contain a valid consignment ID.',
        );
})->with([
    'missing' => [null],
    'float' => [1.5],
    'boolean' => [true],
    'array' => [[]],
]);

it('rejects a missing or empty invoice', function (mixed $value): void {
    $data = steadfastWebhookPayload(['invoice' => $value]);

    expect(fn () => WebhookPayloadData::fromArray($data))
        ->toThrow(
            InvalidWebhookException::class,
            'The webhook payload does not contain a valid invoice.',
        );
})->with([
    'empty' => [''],
    'null' => [null],
    'integer' => [1001],
    'array' => [[]],
]);

it('rejects a missing or empty status', function (mixed $value): void {
    $data = steadfastWebhookPayload(['status' => $value]);

    expect(fn () => WebhookPayloadData::fromArray($data))
        ->toThrow(
            InvalidWebhookException::class,
            'The webhook payload does not contain a valid status.',
        );
})->with([
    'empty' => [''],
    'null' => [null],
    'integer' => [1],
    'array' => [[]],
]);

it('rejects a non-numeric cash-on-delivery amount', function (mixed $value): void {
    $data = steadfastWebhookPayload(['cod_amount' => $value]);

    expect(fn () => WebhookPayloadData::fromArray($data))
        ->toThrow(
            InvalidWebhookException::class,
            'The webhook payload does not contain a valid cash-on-delivery amount.',
        );
})->with([
    'null' => [null],
    'string' => ['free'],
    'boolean' => [true],
    'array' => [[]],
]);

it('rejects a missing or non-string updated_at timestamp', function (mixed $value): void {
    $data = steadfastWebhookPayload(['updated_at' => $value]);

    expect(fn () => WebhookPayloadData::fromArray($data))
        ->toThrow(
            InvalidWebhookException::class,
            'The webhook payload does not contain a valid updated-at timestamp.',
        );
})->with([
    'null' => [null],
    'integer timestamp' => [1730000000],
    'array' => [[]],
]);

it('rejects an unparsable updated_at timestamp and keeps the previous exception', function (): void {
    $data = steadfastWebhookPayload(['updated_at' => 'definitely-not-a-date']);

    try {
        WebhookPayloadData::fromArray($data);

        $this->fail('Expected an InvalidWebhookException.');
    } catch (InvalidWebhookException $exception) {
        expect($exception->getMessage())
            ->toBe('The webhook payload contains an invalid updated-at timestamp.')
            ->and($exception->getPrevious())->not->toBeNull();
    }
});

it('converts the payload to an array', function (): void {
    $payload = new WebhookPayloadData(
        consignmentId: 1424107,
        invoice: 'INV-1001',
        status: DeliveryStatus::Delivered,
        codAmount: 1000.75,
        updatedAt: CarbonImmutable::parse('2026-10-28T12:00:00+06:00'),
        notificationType: 'delivery_status',
    );

    expect($payload->toArray())->toBe([
        'consignment_id' => 1424107,
        'invoice' => 'INV-1001',
        'status' => 'delivered',
        'cod_amount' => 1000.75,
        'updated_at' => '2026-10-28T12:00:00+06:00',
        'notification_type' => 'delivery_status',
    ]);
});
