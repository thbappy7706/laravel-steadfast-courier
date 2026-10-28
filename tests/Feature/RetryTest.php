<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Thbappy7706\SteadFastCourier\Contracts\SteadFastClient;
use Thbappy7706\SteadFastCourier\Enums\DeliveryStatus;
use Thbappy7706\SteadFastCourier\Exceptions\AuthenticationException;
use Thbappy7706\SteadFastCourier\Exceptions\RequestFailedException;
use Thbappy7706\SteadFastCourier\Exceptions\ValidationException;
use Thbappy7706\SteadFastCourier\Tests\TestCase;

uses(TestCase::class);

it('retries a get request after a server error', function (): void {
    Http::fakeSequence()
        ->push(['message' => 'Temporary failure.'], 500)
        ->push(['delivery_status' => 'delivered'], 200);

    $status = app(SteadFastClient::class)->checkDeliveryStatusByConsignmentId(123);

    expect($status->status)->toBe(DeliveryStatus::Delivered);

    Http::assertSentCount(2);
});

it('retries a get request after a rate limit response', function (): void {
    Http::fakeSequence()
        ->push(['message' => 'Too many requests.'], 429)
        ->push(['current_balance' => 50], 200);

    $balance = app(SteadFastClient::class)->getCurrentBalance();

    expect($balance->currentBalance)->toBe(50.0);

    Http::assertSentCount(2);
});

it('retries a get request after a connection failure', function (): void {
    $attempts = 0;

    Http::fake(function () use (&$attempts) {
        $attempts++;

        if ($attempts === 1) {
            throw new ConnectionException('Temporary network failure.');
        }

        return Http::response(['current_balance' => 75], 200);
    });

    $balance = app(SteadFastClient::class)->getCurrentBalance();

    expect($balance->currentBalance)->toBe(75.0)
        ->and($attempts)->toBe(2);
});

it('does not retry authentication failures', function (): void {
    Http::fakeSequence()
        ->push(['message' => 'Unauthenticated.'], 401)
        ->push(['current_balance' => 50], 200);

    expect(fn () => app(SteadFastClient::class)->getCurrentBalance())
        ->toThrow(AuthenticationException::class);

    Http::assertSentCount(1);
});

it('does not retry validation failures', function (): void {
    Http::fakeSequence()
        ->push(['message' => 'Invalid.'], 422)
        ->push(['current_balance' => 50], 200);

    expect(fn () => app(SteadFastClient::class)->getCurrentBalance())
        ->toThrow(ValidationException::class);

    Http::assertSentCount(1);
});

it('does not retry not found responses', function (): void {
    Http::fakeSequence()
        ->push(['message' => 'Not found.'], 404)
        ->push(['current_balance' => 50], 200);

    expect(fn () => app(SteadFastClient::class)->getCurrentBalance())
        ->toThrow(RequestFailedException::class);

    Http::assertSentCount(1);
});

it('stops retrying after the configured number of attempts', function (): void {
    Http::fakeSequence()
        ->push(['message' => 'Still failing.'], 500)
        ->push(['message' => 'Still failing.'], 500)
        ->push(['current_balance' => 50], 200);

    try {
        app(SteadFastClient::class)->getCurrentBalance();

        $this->fail('Expected a RequestFailedException.');
    } catch (RequestFailedException $exception) {
        expect($exception->statusCode)->toBe(500)
            ->and($exception->getMessage())->toBe('Still failing.');
    }

    Http::assertSentCount(2);
});

it('honours a custom retry count', function (): void {
    config()->set('steadfast-courier.retry.times', 3);

    Http::fakeSequence()
        ->push(['message' => 'Still failing.'], 500)
        ->push(['message' => 'Still failing.'], 500)
        ->push(['message' => 'Still failing.'], 500)
        ->push(['current_balance' => 50], 200);

    expect(fn () => app(SteadFastClient::class)->getCurrentBalance())
        ->toThrow(RequestFailedException::class);

    Http::assertSentCount(3);
});

it('does not retry when retries are disabled', function (): void {
    config()->set('steadfast-courier.retry.times', 0);

    Http::fakeSequence()
        ->push(['message' => 'Still failing.'], 500)
        ->push(['current_balance' => 50], 200);

    expect(fn () => app(SteadFastClient::class)->getCurrentBalance())
        ->toThrow(RequestFailedException::class);

    Http::assertSentCount(1);
});

it('never retries order creation', function (): void {
    Http::fakeSequence()
        ->push(['message' => 'Server failure.'], 500)
        ->push(steadfastConsignmentPayload(), 201);

    expect(fn () => app(SteadFastClient::class)->placeOrder(steadfastOrderPayload()))
        ->toThrow(RequestFailedException::class);

    Http::assertSentCount(1);
});

it('never retries order creation on connection failures', function (): void {
    $attempts = 0;

    Http::fake(function () use (&$attempts) {
        $attempts++;

        throw new ConnectionException('Connection refused.');
    });

    expect(fn () => app(SteadFastClient::class)->placeOrder(steadfastOrderPayload()))
        ->toThrow(RequestFailedException::class);

    expect($attempts)->toBe(1);
});
