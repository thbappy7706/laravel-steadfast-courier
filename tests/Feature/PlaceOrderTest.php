<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Thbappy7706\SteadFastCourier\Contracts\SteadFastClient;
use Thbappy7706\SteadFastCourier\Data\ConsignmentData;
use Thbappy7706\SteadFastCourier\Data\OrderData;
use Thbappy7706\SteadFastCourier\Enums\DeliveryStatus;
use Thbappy7706\SteadFastCourier\Enums\DeliveryType;
use Thbappy7706\SteadFastCourier\Exceptions\ConfigurationException;
use Thbappy7706\SteadFastCourier\Exceptions\ValidationException;
use Thbappy7706\SteadFastCourier\Tests\TestCase;

uses(TestCase::class);

it('places an order and maps the consignment', function (): void {
    Http::fake([
        '*' => Http::response(steadfastConsignmentPayload(), 201),
    ]);

    $client = app(SteadFastClient::class);

    $consignment = $client->placeOrder(new OrderData(
        invoice: 'INV-1001',
        recipientName: 'John Doe',
        recipientPhone: '01712345678',
        recipientAddress: 'House 17, Road 3/A, Dhanmondi, Dhaka-1209',
        codAmount: 1000,
        note: 'Handle with care',
        totalLot: 2,
        deliveryType: DeliveryType::PointDelivery,
    ));

    expect($consignment)->toBeInstanceOf(ConsignmentData::class)
        ->and($consignment->consignmentId)->toBe(1424107)
        ->and($consignment->invoice)->toBe('INV-1001')
        ->and($consignment->trackingCode)->toBe('15BAEB8A')
        ->and($consignment->status)->toBe(DeliveryStatus::InReview)
        ->and($consignment->createdAt?->toIso8601String())->toBe('2026-10-28T10:00:00+06:00');

    Http::assertSentCount(1);

    Http::assertSent(function (ClientRequest $request): bool {
        $body = $request->data();

        return $request->method() === 'POST'
            && $request->url() === 'https://portal.steadfast.com.bd/api/v1/create_order'
            && $request->hasHeader('Api-Key', 'test-api-key')
            && $request->hasHeader('Secret-Key', 'test-secret-key')
            && $request->hasHeader('Accept', 'application/json')
            && $request->isJson()
            && $body === [
                'invoice' => 'INV-1001',
                'recipient_name' => 'John Doe',
                'recipient_phone' => '01712345678',
                'recipient_address' => 'House 17, Road 3/A, Dhanmondi, Dhaka-1209',
                'cod_amount' => 1000,
                'note' => 'Handle with care',
                'total_lot' => 2,
                'delivery_type' => 1,
            ];
    });
});

it('places an order from an array payload', function (): void {
    Http::fake([
        '*' => Http::response(steadfastConsignmentPayload(), 201),
    ]);

    $client = app(SteadFastClient::class);

    $client->placeOrder(steadfastOrderPayload([
        'cod_amount' => '500.50',
        'total_lot' => 3,
        'delivery_type' => '1',
    ]));

    Http::assertSent(function (ClientRequest $request): bool {
        $body = $request->data();

        return $body['cod_amount'] === 500.5
            && $body['total_lot'] === 3
            && $body['delivery_type'] === 1
            && ! array_key_exists('alternative_phone', $body);
    });
});

it('does not send an invalid order to the API', function (): void {
    Http::fake();

    $client = app(SteadFastClient::class);

    try {
        $client->placeOrder(steadfastOrderPayload([
            'recipient_phone' => '123',
            'cod_amount' => -1,
        ]));

        $this->fail('Expected a ValidationException.');
    } catch (ValidationException $exception) {
        expect($exception->errors)->toHaveKeys(['recipient_phone', 'cod_amount']);
    }

    Http::assertNothingSent();
});

it('does not send a request when credentials are missing', function (): void {
    Http::fake();

    config()->set('steadfast-courier.api_key', null);

    $client = app(SteadFastClient::class);

    expect(fn () => $client->placeOrder(steadfastOrderPayload()))
        ->toThrow(
            ConfigurationException::class,
            'SteadFast API credentials are not configured.',
        );

    Http::assertNothingSent();
});

it('does not send a request when only the secret key is missing', function (): void {
    Http::fake();

    config()->set('steadfast-courier.secret_key', null);

    $client = app(SteadFastClient::class);

    expect(fn () => $client->getCurrentBalance())->toThrow(ConfigurationException::class);

    Http::assertNothingSent();
});

it('maps a nested consignment response', function (): void {
    Http::fake([
        '*' => Http::response(['consignment' => steadfastConsignmentPayload(['status' => 'pending'])], 200),
    ]);

    $consignment = app(SteadFastClient::class)->placeOrder(steadfastOrderPayload());

    expect($consignment->status)->toBe(DeliveryStatus::Pending)
        ->and($consignment->consignmentId)->toBe(1424107);
});
