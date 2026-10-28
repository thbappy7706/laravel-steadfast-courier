<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Thbappy7706\SteadFastCourier\Data\ConsignmentData;
use Thbappy7706\SteadFastCourier\Enums\DeliveryStatus;
use Thbappy7706\SteadFastCourier\Exceptions\RequestFailedException;

it('maps a complete consignment response', function (): void {
    $consignment = ConsignmentData::fromArray(steadfastConsignmentPayload());

    expect($consignment->consignmentId)->toBe(1424107)
        ->and($consignment->invoice)->toBe('INV-1001')
        ->and($consignment->trackingCode)->toBe('15BAEB8A')
        ->and($consignment->status)->toBe(DeliveryStatus::InReview)
        ->and($consignment->recipientName)->toBe('John Doe')
        ->and($consignment->recipientPhone)->toBe('01712345678')
        ->and($consignment->recipientAddress)->toBe('House 17, Road 3/A, Dhanmondi, Dhaka-1209')
        ->and($consignment->codAmount)->toBe(1000)
        ->and($consignment->note)->toBe('Handle with care')
        ->and($consignment->createdAt)->toBeInstanceOf(CarbonImmutable::class)
        ->and($consignment->createdAt?->toIso8601String())->toBe('2026-10-28T10:00:00+06:00')
        ->and($consignment->updatedAt?->toIso8601String())->toBe('2026-10-28T11:00:00+06:00');
});

it('reads a response nested under the consignment key', function (): void {
    $consignment = ConsignmentData::fromArray([
        'consignment' => steadfastConsignmentPayload(),
    ]);

    expect($consignment->consignmentId)->toBe(1424107)
        ->and($consignment->trackingCode)->toBe('15BAEB8A');
});

it('accepts a string consignment id and a decimal cash-on-delivery amount', function (): void {
    $consignment = ConsignmentData::fromArray(steadfastConsignmentPayload([
        'consignment_id' => '1424107',
        'cod_amount' => '1000.50',
    ]));

    expect($consignment->consignmentId)->toBe('1424107')
        ->and($consignment->codAmount)->toBe(1000.5);
});

it('falls back to the unknown status for unrecognised values', function (mixed $status): void {
    $payload = steadfastConsignmentPayload();

    if ($status === null) {
        unset($payload['status']);
    } else {
        $payload['status'] = $status;
    }

    expect(ConsignmentData::fromArray($payload)->status)->toBe(DeliveryStatus::Unknown);
})->with([
    'missing' => [null],
    'unrecognised' => ['lost_in_space'],
    'non-string' => [123],
]);

it('maps every known delivery status', function (string $status, DeliveryStatus $expected): void {
    $consignment = ConsignmentData::fromArray(steadfastConsignmentPayload(['status' => $status]));

    expect($consignment->status)->toBe($expected);
})->with([
    ['pending', DeliveryStatus::Pending],
    ['delivered', DeliveryStatus::Delivered],
    ['partial_delivered', DeliveryStatus::PartialDelivered],
    ['cancelled', DeliveryStatus::Cancelled],
    ['hold', DeliveryStatus::Hold],
    ['in_review', DeliveryStatus::InReview],
]);

it('defaults optional string and numeric fields to null', function (): void {
    $consignment = ConsignmentData::fromArray([
        'consignment_id' => 1,
        'tracking_code' => 'TRACK',
    ]);

    expect($consignment->invoice)->toBe('')
        ->and($consignment->status)->toBe(DeliveryStatus::Unknown)
        ->and($consignment->recipientName)->toBeNull()
        ->and($consignment->recipientPhone)->toBeNull()
        ->and($consignment->recipientAddress)->toBeNull()
        ->and($consignment->codAmount)->toBeNull()
        ->and($consignment->note)->toBeNull()
        ->and($consignment->createdAt)->toBeNull()
        ->and($consignment->updatedAt)->toBeNull();
});

it('ignores non-string optional values', function (): void {
    $consignment = ConsignmentData::fromArray(steadfastConsignmentPayload([
        'invoice' => 1001,
        'recipient_name' => 123,
        'recipient_phone' => ['01712345678'],
        'recipient_address' => false,
        'cod_amount' => 'free',
        'note' => 42,
    ]));

    expect($consignment->invoice)->toBe('')
        ->and($consignment->recipientName)->toBeNull()
        ->and($consignment->recipientPhone)->toBeNull()
        ->and($consignment->recipientAddress)->toBeNull()
        ->and($consignment->codAmount)->toBeNull()
        ->and($consignment->note)->toBeNull();
});

it('ignores unparsable dates', function (): void {
    $consignment = ConsignmentData::fromArray(steadfastConsignmentPayload([
        'created_at' => 'not-a-date',
        'updated_at' => '',
    ]));

    expect($consignment->createdAt)->toBeNull()
        ->and($consignment->updatedAt)->toBeNull();
});

it('ignores non-string dates', function (mixed $value): void {
    $consignment = ConsignmentData::fromArray(steadfastConsignmentPayload([
        'created_at' => $value,
        'updated_at' => $value,
    ]));

    expect($consignment->createdAt)->toBeNull()
        ->and($consignment->updatedAt)->toBeNull();
})->with([
    'integer timestamp' => [1730000000],
    'array' => [[]],
    'boolean' => [true],
]);

it('throws when the consignment id is missing', function (mixed $value): void {
    $payload = steadfastConsignmentPayload();

    if ($value === null) {
        unset($payload['consignment_id']);
    } else {
        $payload['consignment_id'] = $value;
    }

    expect(fn () => ConsignmentData::fromArray($payload))
        ->toThrow(
            RequestFailedException::class,
            'The SteadFast API response did not contain a consignment ID.',
        );
})->with([
    'missing' => [null],
    'float' => [1.5],
    'boolean' => [true],
    'array' => [[]],
]);

it('throws when the tracking code is missing', function (mixed $value): void {
    $payload = steadfastConsignmentPayload();

    if ($value === null) {
        unset($payload['tracking_code']);
    } else {
        $payload['tracking_code'] = $value;
    }

    expect(fn () => ConsignmentData::fromArray($payload))
        ->toThrow(
            RequestFailedException::class,
            'The SteadFast API response did not contain a tracking code.',
        );
})->with([
    'missing' => [null],
    'integer' => [123],
    'array' => [[]],
]);

it('converts the consignment to an array', function (): void {
    $consignment = new ConsignmentData(
        consignmentId: 1424107,
        invoice: 'INV-1001',
        trackingCode: '15BAEB8A',
        status: DeliveryStatus::Delivered,
        recipientName: 'John Doe',
        recipientPhone: '01712345678',
        recipientAddress: 'Dhaka',
        codAmount: 1000.5,
        note: 'Delivered',
        createdAt: CarbonImmutable::parse('2026-10-28T10:00:00+06:00'),
        updatedAt: CarbonImmutable::parse('2026-10-28T11:00:00+06:00'),
    );

    expect($consignment->toArray())->toBe([
        'consignment_id' => 1424107,
        'invoice' => 'INV-1001',
        'tracking_code' => '15BAEB8A',
        'status' => 'delivered',
        'recipient_name' => 'John Doe',
        'recipient_phone' => '01712345678',
        'recipient_address' => 'Dhaka',
        'cod_amount' => 1000.5,
        'note' => 'Delivered',
        'created_at' => '2026-10-28T10:00:00+06:00',
        'updated_at' => '2026-10-28T11:00:00+06:00',
    ]);
});

it('keeps null dates null when converting to an array', function (): void {
    $consignment = new ConsignmentData(
        consignmentId: 1,
        invoice: 'INV-1',
        trackingCode: 'TRACK',
        status: DeliveryStatus::Pending,
    );

    expect($consignment->toArray()['created_at'])->toBeNull()
        ->and($consignment->toArray()['updated_at'])->toBeNull();
});
