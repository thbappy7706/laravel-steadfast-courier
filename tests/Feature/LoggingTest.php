<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Thbappy7706\SteadFastCourier\Contracts\SteadFastClient;
use Thbappy7706\SteadFastCourier\Exceptions\RequestFailedException;
use Thbappy7706\SteadFastCourier\Tests\TestCase;

uses(TestCase::class);

it('does not log when logging is disabled', function (): void {
    Http::fake([
        '*' => Http::response(['current_balance' => 10]),
    ]);

    Log::spy();

    app(SteadFastClient::class)->getCurrentBalance();

    Log::shouldNotHaveReceived('debug');
});

it('logs the method, url and status of successful requests', function (): void {
    config()->set('steadfast-courier.logging.enabled', true);

    Http::fake([
        '*' => Http::response(['current_balance' => 10]),
    ]);

    Log::spy();

    app(SteadFastClient::class)->getCurrentBalance();

    Log::shouldHaveReceived('debug')
        ->once()
        ->with(
            'SteadFast API request',
            Mockery::on(function (array $context): bool {
                expect($context)->toBe([
                    'method' => 'GET',
                    'url' => 'https://portal.steadfast.com.bd/api/v1/get_balance',
                    'status' => 200,
                ]);

                return true;
            }),
        );
});

it('logs the status of POST requests', function (): void {
    config()->set('steadfast-courier.logging.enabled', true);

    Http::fake([
        '*' => Http::response(steadfastConsignmentPayload(), 201),
    ]);

    Log::spy();

    app(SteadFastClient::class)->placeOrder(steadfastOrderPayload());

    Log::shouldHaveReceived('debug')
        ->once()
        ->with(
            'SteadFast API request',
            Mockery::on(fn (array $context): bool => $context['method'] === 'POST'
                && $context['url'] === 'https://portal.steadfast.com.bd/api/v1/create_order'
                && $context['status'] === 201),
        );
});

it('never logs credentials or request bodies', function (): void {
    config()->set('steadfast-courier.logging.enabled', true);

    Http::fake([
        '*' => Http::response(steadfastConsignmentPayload(), 201),
    ]);

    Log::spy();

    app(SteadFastClient::class)->placeOrder(steadfastOrderPayload([
        'invoice' => 'SENSITIVE-INVOICE',
        'recipient_phone' => '01712345678',
    ]));

    Log::shouldHaveReceived('debug')
        ->once()
        ->with(
            'SteadFast API request',
            Mockery::on(function (array $context): bool {
                $encoded = json_encode($context);

                expect(array_keys($context))->toBe(['method', 'url', 'status'])
                    ->and($encoded)->not->toContain('test-api-key')
                    ->and($encoded)->not->toContain('test-secret-key')
                    ->and($encoded)->not->toContain('SENSITIVE-INVOICE')
                    ->and($encoded)->not->toContain('01712345678');

                return true;
            }),
        );
});

it('writes to the configured log channel', function (): void {
    config()->set('steadfast-courier.logging.enabled', true);
    config()->set('steadfast-courier.logging.channel', 'steadfast');

    Http::fake([
        '*' => Http::response(['current_balance' => 10]),
    ]);

    Log::spy();
    Log::shouldReceive('channel')->once()->with('steadfast')->andReturnSelf();
    Log::shouldReceive('debug')->once()->with('SteadFast API request', Mockery::type('array'));

    app(SteadFastClient::class)->getCurrentBalance();
});

it('does not log failed requests', function (): void {
    config()->set('steadfast-courier.logging.enabled', true);

    Http::fake([
        '*' => Http::response(['message' => 'Failure'], 500),
    ]);

    Log::spy();

    expect(fn () => app(SteadFastClient::class)->getCurrentBalance())
        ->toThrow(RequestFailedException::class);

    Log::shouldNotHaveReceived('debug');
});
