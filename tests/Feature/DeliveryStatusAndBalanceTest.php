<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Thbappy7706\SteadFastCourier\Contracts\SteadFastClient;
use Thbappy7706\SteadFastCourier\Data\BalanceData;
use Thbappy7706\SteadFastCourier\Data\DeliveryStatusData;
use Thbappy7706\SteadFastCourier\Enums\DeliveryStatus;
use Thbappy7706\SteadFastCourier\Tests\TestCase;

uses(TestCase::class);

it('checks delivery status by consignment id', function (): void {
    Http::fake([
        '*' => Http::response(['delivery_status' => 'delivered']),
    ]);

    $status = app(SteadFastClient::class)->checkDeliveryStatusByConsignmentId(1424107);

    expect($status)->toBeInstanceOf(DeliveryStatusData::class)
        ->and($status->status)->toBe(DeliveryStatus::Delivered);

    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'GET'
        && $request->url() === 'https://portal.steadfast.com.bd/api/v1/status_by_cid/1424107'
        && $request->hasHeader('Api-Key', 'test-api-key')
        && $request->hasHeader('Secret-Key', 'test-secret-key'));
});

it('url encodes the consignment id', function (): void {
    Http::fake([
        '*' => Http::response(['delivery_status' => 'pending']),
    ]);

    $status = app(SteadFastClient::class)->checkDeliveryStatusByConsignmentId('abc 123');

    expect($status->status)->toBe(DeliveryStatus::Pending);

    Http::assertSent(fn (ClientRequest $request): bool => str_ends_with($request->url(), '/status_by_cid/abc%20123'));
});

it('checks delivery status by invoice id', function (): void {
    Http::fake([
        '*' => Http::response(['status' => 'in_review']),
    ]);

    $status = app(SteadFastClient::class)->checkDeliveryStatusByInvoiceId('INV/1001');

    expect($status->status)->toBe(DeliveryStatus::InReview);

    Http::assertSent(fn (ClientRequest $request): bool => str_ends_with($request->url(), '/status_by_invoice/INV%2F1001'));
});

it('checks delivery status by tracking code', function (): void {
    Http::fake([
        '*' => Http::response(['delivery_status' => 'partial_delivered']),
    ]);

    $status = app(SteadFastClient::class)->checkDeliveryStatusByTrackingCode('15BAEB8A');

    expect($status->status)->toBe(DeliveryStatus::PartialDelivered);

    Http::assertSent(fn (ClientRequest $request): bool => str_ends_with($request->url(), '/status_by_trackingcode/15BAEB8A'));
});

it('returns an unknown status for unrecognised API values', function (): void {
    Http::fake([
        '*' => Http::response(['delivery_status' => 'delivery_robot_dispatched']),
    ]);

    $status = app(SteadFastClient::class)->checkDeliveryStatusByTrackingCode('TRACK');

    expect($status->status)->toBe(DeliveryStatus::Unknown);
});

it('reads the current balance', function (): void {
    Http::fake([
        '*' => Http::response(['current_balance' => 1250.75]),
    ]);

    $balance = app(SteadFastClient::class)->getCurrentBalance();

    expect($balance)->toBeInstanceOf(BalanceData::class)
        ->and($balance->currentBalance)->toBe(1250.75);

    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'GET'
        && $request->url() === 'https://portal.steadfast.com.bd/api/v1/get_balance');
});

it('sends no payload with GET requests', function (): void {
    Http::fake([
        '*' => Http::response(['current_balance' => 0]),
    ]);

    app(SteadFastClient::class)->getCurrentBalance();

    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'GET'
        && $request->body() === '');
});
