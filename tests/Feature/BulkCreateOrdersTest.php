<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Thbappy7706\SteadFastCourier\Contracts\SteadFastClient;
use Thbappy7706\SteadFastCourier\Data\BulkOrderResultData;
use Thbappy7706\SteadFastCourier\Data\OrderData;
use Thbappy7706\SteadFastCourier\Exceptions\RequestFailedException;
use Thbappy7706\SteadFastCourier\Tests\TestCase;

uses(TestCase::class);

it('returns an empty list for an empty iterable without calling the API', function (iterable $orders): void {
    Http::fake();

    expect(app(SteadFastClient::class)->bulkCreateOrders($orders))->toBe([]);

    Http::assertNothingSent();
})->with([
    'empty array' => [[]],
    'empty generator' => [(static function (): Generator {
        yield from [];
    })()],
]);

it('chunks bulk orders and preserves the input order', function (): void {
    config()->set('steadfast-courier.bulk_chunk_size', 2);

    Http::fakeSequence()
        ->push([
            ['invoice' => 'INV-2', 'status' => 'success', 'consignment_id' => 2, 'tracking_code' => 'TWO'],
            ['invoice' => 'INV-1', 'status' => 'success', 'consignment_id' => 1, 'tracking_code' => 'ONE'],
        ])
        ->push([
            'data' => [
                ['invoice' => 'INV-3', 'status' => 'success', 'consignment_id' => 3, 'tracking_code' => 'THREE'],
            ],
        ]);

    $orders = [
        steadfastOrderPayload(['invoice' => 'INV-1']),
        new OrderData(
            invoice: 'INV-2',
            recipientName: 'Jane Smith',
            recipientPhone: '01812345678',
            recipientAddress: 'Uttara, Dhaka',
            codAmount: 1500,
        ),
        steadfastOrderPayload(['invoice' => 'INV-3']),
    ];

    $results = app(SteadFastClient::class)->bulkCreateOrders($orders);

    expect($results)->toHaveCount(3)
        ->and(array_map(static fn (BulkOrderResultData $result): string => $result->invoice, $results))
        ->toBe(['INV-1', 'INV-2', 'INV-3'])
        ->and(array_map(static fn (BulkOrderResultData $result): int|string|null => $result->consignmentId, $results))
        ->toBe([1, 2, 3])
        ->and(array_map(static fn (BulkOrderResultData $result): bool => $result->successful, $results))
        ->toBe([true, true, true]);

    Http::assertSentCount(2);

    $requests = Http::recorded()->map(static fn (array $pair): ClientRequest => $pair[0]);

    expect($requests[0]->url())->toBe('https://portal.steadfast.com.bd/api/v1/create_order/bulk-create')
        ->and($requests[0]->hasHeader('Api-Key', 'test-api-key'))->toBeTrue()
        ->and($requests[0]->hasHeader('Secret-Key', 'test-secret-key'))->toBeTrue()
        ->and($requests[0]->data()['data'])->toHaveCount(2)
        ->and(array_column($requests[0]->data()['data'], 'invoice'))->toBe(['INV-1', 'INV-2'])
        ->and($requests[1]->data()['data'])->toHaveCount(1)
        ->and($requests[1]->data()['data'][0]['invoice'])->toBe('INV-3');
});

it('matches results by invoice regardless of response order', function (): void {
    Http::fake([
        '*' => Http::response([
            ['invoice' => 'INV-B', 'status' => 'success', 'consignment_id' => 22],
            ['invoice' => 'INV-A', 'status' => 'success', 'consignment_id' => 11],
        ]),
    ]);

    $results = app(SteadFastClient::class)->bulkCreateOrders([
        steadfastOrderPayload(['invoice' => 'INV-A']),
        steadfastOrderPayload(['invoice' => 'INV-B']),
    ]);

    expect($results[0]->invoice)->toBe('INV-A')
        ->and($results[0]->consignmentId)->toBe(11)
        ->and($results[1]->invoice)->toBe('INV-B')
        ->and($results[1]->consignmentId)->toBe(22);
});

it('falls back to positional results when the response has no invoices', function (): void {
    Http::fake([
        '*' => Http::response([
            ['status' => 'success', 'consignment_id' => 11],
            ['status' => 'success', 'consignment_id' => 22],
        ]),
    ]);

    $results = app(SteadFastClient::class)->bulkCreateOrders([
        steadfastOrderPayload(['invoice' => 'INV-A']),
        steadfastOrderPayload(['invoice' => 'INV-B']),
    ]);

    expect($results)->toHaveCount(2)
        ->and($results[0]->successful)->toBeTrue()
        ->and($results[0]->consignmentId)->toBe(11)
        ->and($results[1]->successful)->toBeTrue()
        ->and($results[1]->consignmentId)->toBe(22);
});

it('reports an error when the API omits a result for an order', function (): void {
    Http::fake([
        '*' => Http::response([
            ['invoice' => 'INV-1', 'status' => 'success', 'consignment_id' => 1],
        ]),
    ]);

    $results = app(SteadFastClient::class)->bulkCreateOrders([
        steadfastOrderPayload(['invoice' => 'INV-1']),
        steadfastOrderPayload(['invoice' => 'INV-2']),
    ]);

    expect($results[0]->successful)->toBeTrue()
        ->and($results[1]->invoice)->toBe('INV-2')
        ->and($results[1]->successful)->toBeFalse()
        ->and($results[1]->error)->toBe('The SteadFast API did not return a result for this order.');
});

it('maps failed items returned by the API', function (): void {
    Http::fake([
        '*' => Http::response([
            [
                'invoice' => 'INV-1',
                'status' => 'error',
                'message' => 'The invoice has already been taken.',
            ],
        ]),
    ]);

    $results = app(SteadFastClient::class)->bulkCreateOrders([
        steadfastOrderPayload(['invoice' => 'INV-1']),
    ]);

    expect($results[0]->successful)->toBeFalse()
        ->and($results[0]->status)->toBe('error')
        ->and($results[0]->error)->toBe('The invoice has already been taken.')
        ->and($results[0]->consignmentId)->toBeNull();
});

it('does not abort the batch for an invalid order', function (): void {
    Http::fake([
        '*' => Http::response([
            ['invoice' => 'INV-2', 'status' => 'success', 'consignment_id' => 2],
        ]),
    ]);

    $results = app(SteadFastClient::class)->bulkCreateOrders([
        steadfastOrderPayload(['invoice' => 'BAD-1', 'recipient_phone' => '123']),
        steadfastOrderPayload(['invoice' => 'INV-2']),
    ]);

    expect($results[0]->invoice)->toBe('BAD-1')
        ->and($results[0]->successful)->toBeFalse()
        ->and($results[0]->error)->toBe('The order data is invalid.')
        ->and($results[1]->invoice)->toBe('INV-2')
        ->and($results[1]->successful)->toBeTrue();

    Http::assertSentCount(1);
    Http::assertSent(fn (ClientRequest $request): bool => array_column($request->data()['data'], 'invoice') === ['INV-2']);
});

it('keeps an empty invoice when an invalid order has none', function (): void {
    Http::fake();

    $results = app(SteadFastClient::class)->bulkCreateOrders([
        ['recipient_name' => 'John Doe'],
    ]);

    expect($results[0]->invoice)->toBe('')
        ->and($results[0]->successful)->toBeFalse()
        ->and($results[0]->error)->toBe('The order data is invalid.');

    Http::assertNothingSent();
});

it('rejects values that are neither an order nor an array', function (mixed $value): void {
    Http::fake();

    $results = app(SteadFastClient::class)->bulkCreateOrders([$value]);

    expect($results[0])->toBeInstanceOf(BulkOrderResultData::class)
        ->and($results[0]->invoice)->toBe('')
        ->and($results[0]->successful)->toBeFalse()
        ->and($results[0]->error)->toBe('Every order must be an OrderData instance or an array.');

    Http::assertNothingSent();
})->with([
    'string' => ['INV-1'],
    'integer' => [123],
    'null' => [null],
    'object' => [new stdClass],
]);

it('returns per-order failures when every order is invalid', function (): void {
    Http::fake();

    $results = app(SteadFastClient::class)->bulkCreateOrders([
        steadfastOrderPayload(['invoice' => 'BAD-1', 'recipient_phone' => '123']),
        'not-an-order',
    ]);

    expect($results)->toHaveCount(2)
        ->and($results[0]->successful)->toBeFalse()
        ->and($results[1]->successful)->toBeFalse();

    Http::assertNothingSent();
});

it('accepts a generator of orders', function (): void {
    Http::fake([
        '*' => Http::response([
            ['invoice' => 'INV-1', 'status' => 'success', 'consignment_id' => 1],
        ]),
    ]);

    $generator = (static function (): Generator {
        yield steadfastOrderPayload(['invoice' => 'INV-1']);
    })();

    $results = app(SteadFastClient::class)->bulkCreateOrders($generator);

    expect($results)->toHaveCount(1)
        ->and($results[0]->successful)->toBeTrue();

    Http::assertSentCount(1);
});

it('throws when the bulk response shape is invalid', function (mixed $body): void {
    Http::fake([
        '*' => Http::response($body, 200),
    ]);

    expect(fn () => app(SteadFastClient::class)->bulkCreateOrders([
        steadfastOrderPayload(['invoice' => 'INV-1']),
    ]))->toThrow(
        RequestFailedException::class,
        'The SteadFast API returned an unexpected bulk order response.',
    );
})->with([
    'associative without data' => [['unexpected' => true]],
    'data is a string' => [['data' => 'nope']],
    'data is null' => [['data' => null]],
]);

it('skips non-array items in the bulk response', function (): void {
    Http::fake([
        '*' => Http::response([
            'data' => [
                'not-an-object',
                ['invoice' => 'INV-1', 'status' => 'success', 'consignment_id' => 1],
            ],
        ]),
    ]);

    $results = app(SteadFastClient::class)->bulkCreateOrders([
        steadfastOrderPayload(['invoice' => 'INV-1']),
    ]);

    expect($results[0]->successful)->toBeTrue()
        ->and($results[0]->consignmentId)->toBe(1);
});
