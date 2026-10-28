<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Thbappy7706\SteadFastCourier\Contracts\SteadFastClient;
use Thbappy7706\SteadFastCourier\Exceptions\AuthenticationException;
use Thbappy7706\SteadFastCourier\Exceptions\RequestFailedException;
use Thbappy7706\SteadFastCourier\Exceptions\SteadFastException;
use Thbappy7706\SteadFastCourier\Exceptions\ValidationException;
use Thbappy7706\SteadFastCourier\Tests\TestCase;

uses(TestCase::class);

it('maps unauthorised responses to an authentication exception', function (int $status): void {
    Http::fake([
        '*' => Http::response(['message' => 'Invalid credentials.'], $status),
    ]);

    try {
        app(SteadFastClient::class)->getCurrentBalance();

        $this->fail('Expected an AuthenticationException.');
    } catch (AuthenticationException $exception) {
        expect($exception->getMessage())->toBe('Invalid credentials.')
            ->and($exception->getCode())->toBe($status)
            ->and($exception)->toBeInstanceOf(SteadFastException::class);
    }
})->with([
    'unauthorised' => [401],
    'forbidden' => [403],
]);

it('maps validation failures to an exception carrying field errors', function (int $status): void {
    Http::fake([
        '*' => Http::response([
            'message' => 'The given data was invalid.',
            'errors' => [
                'invoice' => ['The invoice has already been taken.'],
                'recipient_phone' => ['The recipient phone is invalid.'],
            ],
        ], $status),
    ]);

    try {
        app(SteadFastClient::class)->placeOrder(steadfastOrderPayload());

        $this->fail('Expected a ValidationException.');
    } catch (ValidationException $exception) {
        expect($exception->getMessage())->toBe('The given data was invalid.')
            ->and($exception->getCode())->toBe($status)
            ->and($exception->errors)->toHaveKeys(['invoice', 'recipient_phone']);
    }
})->with([
    'bad request' => [400],
    'unprocessable entity' => [422],
]);

it('defaults validation errors to an empty array when the body has none', function (): void {
    Http::fake([
        '*' => Http::response(['message' => 'Invalid'], 422),
    ]);

    try {
        app(SteadFastClient::class)->getCurrentBalance();

        $this->fail('Expected a ValidationException.');
    } catch (ValidationException $exception) {
        expect($exception->errors)->toBe([]);
    }
});

it('maps server errors to a request failed exception', function (): void {
    Http::fake([
        '*' => Http::response(['message' => 'Something exploded.'], 500),
    ]);

    try {
        app(SteadFastClient::class)->getCurrentBalance();

        $this->fail('Expected a RequestFailedException.');
    } catch (RequestFailedException $exception) {
        expect($exception->getMessage())->toBe('Something exploded.')
            ->and($exception->statusCode)->toBe(500)
            ->and($exception->getCode())->toBe(500)
            ->and($exception->responseBody)->toContain('Something exploded.');
    }
});

it('uses a generic message when the response has none', function (): void {
    Http::fake([
        '*' => Http::response([], 404),
    ]);

    expect(fn () => app(SteadFastClient::class)->getCurrentBalance())
        ->toThrow(
            RequestFailedException::class,
            'The SteadFast API request failed with status 404.',
        );
});

it('uses a generic message when the message is empty', function (): void {
    Http::fake([
        '*' => Http::response(['message' => ''], 418),
    ]);

    expect(fn () => app(SteadFastClient::class)->getCurrentBalance())
        ->toThrow(RequestFailedException::class, 'The SteadFast API request failed with status 418.');
});

it('rejects a non json response body', function (mixed $body): void {
    Http::fake([
        '*' => Http::response($body, 200),
    ]);

    try {
        app(SteadFastClient::class)->getCurrentBalance();

        $this->fail('Expected a RequestFailedException.');
    } catch (RequestFailedException $exception) {
        expect($exception->getMessage())->toBe('The SteadFast API returned an unexpected response body.')
            ->and($exception->statusCode)->toBe(200)
            ->and($exception->responseBody)->not->toBeNull();
    }
})->with([
    'html' => ['<html>oops</html>'],
    'empty' => [''],
    'json scalar' => ['"just-a-string"'],
    'json number' => ['123'],
]);

it('wraps connection failures in a request failed exception', function (): void {
    $attempts = 0;

    Http::fake(function () use (&$attempts) {
        $attempts++;

        throw new ConnectionException('Connection refused.');
    });

    try {
        app(SteadFastClient::class)->getCurrentBalance();

        $this->fail('Expected a RequestFailedException.');
    } catch (RequestFailedException $exception) {
        expect($exception->getMessage())
            ->toBe('Could not connect to the SteadFast API: Connection refused.')
            ->and($exception->statusCode)->toBeNull()
            ->and($exception->getPrevious())->toBeInstanceOf(ConnectionException::class);
    }

    expect($attempts)->toBe(2);
});

it('does not retry order creation failures', function (): void {
    Http::fake([
        '*' => Http::response(['message' => 'Server error.'], 500),
    ]);

    expect(fn () => app(SteadFastClient::class)->placeOrder(steadfastOrderPayload()))
        ->toThrow(RequestFailedException::class);

    Http::assertSentCount(1);
});

it('maps a 200 response without a consignment id to a request failed exception', function (): void {
    Http::fake([
        '*' => Http::response(['unexpected' => true], 200),
    ]);

    expect(fn () => app(SteadFastClient::class)->placeOrder(steadfastOrderPayload()))
        ->toThrow(
            RequestFailedException::class,
            'The SteadFast API response did not contain a consignment ID.',
        );
});
