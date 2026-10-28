<?php

declare(strict_types=1);

use Thbappy7706\SteadFastCourier\Data\BulkOrderResultData;

it('maps a successful result', function (): void {
    $result = BulkOrderResultData::fromArray([
        'invoice' => 'INV-1001',
        'status' => 'success',
        'consignment_id' => 1424107,
        'tracking_code' => '15BAEB8A',
    ]);

    expect($result->invoice)->toBe('INV-1001')
        ->and($result->successful)->toBeTrue()
        ->and($result->consignmentId)->toBe(1424107)
        ->and($result->trackingCode)->toBe('15BAEB8A')
        ->and($result->status)->toBe('success')
        ->and($result->error)->toBeNull();
});

it('accepts a boolean successful flag', function (): void {
    $result = BulkOrderResultData::fromArray([
        'invoice' => 'INV-1001',
        'successful' => true,
    ]);

    expect($result->successful)->toBeTrue();
});

it('maps an unsuccessful result with an error message', function (): void {
    $result = BulkOrderResultData::fromArray([
        'invoice' => 'INV-1002',
        'status' => 'error',
        'message' => 'Duplicate invoice.',
    ]);

    expect($result->successful)->toBeFalse()
        ->and($result->status)->toBe('error')
        ->and($result->error)->toBe('Duplicate invoice.');
});

it('prefers the error key over the message key', function (): void {
    $result = BulkOrderResultData::fromArray([
        'invoice' => 'INV-1002',
        'error' => 'Explicit error',
        'message' => 'Generic message',
    ]);

    expect($result->error)->toBe('Explicit error');
});

it('joins array error messages', function (): void {
    $result = BulkOrderResultData::fromArray([
        'invoice' => 'INV-1002',
        'errors' => ['Phone is invalid.', 123, 'Address is invalid.'],
    ]);

    expect($result->error)->toBe('Phone is invalid. Address is invalid.');
});

it('accepts a string errors field', function (): void {
    $result = BulkOrderResultData::fromArray([
        'invoice' => 'INV-1002',
        'errors' => 'Something went wrong.',
    ]);

    expect($result->error)->toBe('Something went wrong.');
});

it('returns null when no usable error message exists', function (array $payload): void {
    $result = BulkOrderResultData::fromArray($payload);

    expect($result->error)->toBeNull();
})->with([
    'no error keys' => [['invoice' => 'INV-1']],
    'empty error' => [['invoice' => 'INV-1', 'error' => '']],
    'empty error array' => [['invoice' => 'INV-1', 'errors' => []]],
    'array without strings' => [['invoice' => 'INV-1', 'errors' => [1, 2, 3]]],
    'non-string error' => [['invoice' => 'INV-1', 'error' => 123]],
]);

it('casts an integer invoice to a string and defaults a missing invoice', function (mixed $invoice, string $expected): void {
    $payload = ['status' => 'success'];

    if ($invoice !== null) {
        $payload['invoice'] = $invoice;
    }

    expect(BulkOrderResultData::fromArray($payload)->invoice)->toBe($expected);
})->with([
    'integer' => [1001, '1001'],
    'string' => ['INV-1001', 'INV-1001'],
    'missing' => [null, ''],
    'array' => [[], ''],
]);

it('ignores non-string consignment, tracking and status values', function (): void {
    $result = BulkOrderResultData::fromArray([
        'invoice' => 'INV-1',
        'consignment_id' => 1.5,
        'tracking_code' => 123,
        'status' => ['success'],
    ]);

    expect($result->consignmentId)->toBeNull()
        ->and($result->trackingCode)->toBeNull()
        ->and($result->status)->toBeNull()
        ->and($result->successful)->toBeFalse();
});

it('accepts a string consignment id', function (): void {
    $result = BulkOrderResultData::fromArray([
        'invoice' => 'INV-1',
        'consignment_id' => '1424107',
    ]);

    expect($result->consignmentId)->toBe('1424107');
});

it('converts the result to an array', function (): void {
    $result = new BulkOrderResultData(
        invoice: 'INV-1001',
        successful: true,
        consignmentId: 1424107,
        trackingCode: '15BAEB8A',
        status: 'success',
    );

    expect($result->toArray())->toBe([
        'invoice' => 'INV-1001',
        'successful' => true,
        'consignment_id' => 1424107,
        'tracking_code' => '15BAEB8A',
        'status' => 'success',
        'error' => null,
    ]);
});
