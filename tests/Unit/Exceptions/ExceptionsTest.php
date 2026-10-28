<?php

declare(strict_types=1);

use Thbappy7706\SteadFastCourier\Exceptions\AuthenticationException;
use Thbappy7706\SteadFastCourier\Exceptions\ConfigurationException;
use Thbappy7706\SteadFastCourier\Exceptions\InvalidWebhookException;
use Thbappy7706\SteadFastCourier\Exceptions\RequestFailedException;
use Thbappy7706\SteadFastCourier\Exceptions\SteadFastException;
use Thbappy7706\SteadFastCourier\Exceptions\ValidationException;

it('exposes a single exception hierarchy', function (string $class): void {
    expect(is_subclass_of($class, SteadFastException::class))->toBeTrue();
})->with([
    AuthenticationException::class,
    ConfigurationException::class,
    InvalidWebhookException::class,
    RequestFailedException::class,
    ValidationException::class,
]);

it('extends the runtime exception', function (): void {
    expect(new SteadFastException('boom'))->toBeInstanceOf(RuntimeException::class);
});

it('creates a missing credentials configuration exception', function (): void {
    $exception = ConfigurationException::missingCredentials();

    expect($exception)->toBeInstanceOf(ConfigurationException::class)
        ->and($exception->getMessage())->toBe(
            'SteadFast API credentials are not configured. Set STEADFAST_API_KEY and STEADFAST_SECRET_KEY.',
        );
});

it('creates a missing webhook token configuration exception', function (): void {
    $exception = ConfigurationException::missingWebhookToken();

    expect($exception)->toBeInstanceOf(ConfigurationException::class)
        ->and($exception->getMessage())->toBe(
            'The SteadFast webhook bearer token is not configured. Set STEADFAST_BEARER_TOKEN.',
        );
});

it('creates a validation exception from an API response', function (): void {
    $exception = ValidationException::fromResponse([
        'message' => 'The given data was invalid.',
        'errors' => [
            'recipient_phone' => ['The recipient phone is invalid.'],
        ],
    ], 422);

    expect($exception->getMessage())->toBe('The given data was invalid.')
        ->and($exception->getCode())->toBe(422)
        ->and($exception->errors)->toBe([
            'recipient_phone' => ['The recipient phone is invalid.'],
        ]);
});

it('uses a fallback message when the response has no message', function (): void {
    $exception = ValidationException::fromResponse([], 400);

    expect($exception->getMessage())->toBe('The SteadFast API rejected the request.')
        ->and($exception->errors)->toBe([]);
});

it('ignores non-array errors in an API response', function (): void {
    $exception = ValidationException::fromResponse([
        'message' => 'Invalid',
        'errors' => 'not-an-array',
    ], 422);

    expect($exception->errors)->toBe([]);
});

it('prefers an explicitly supplied message', function (): void {
    $exception = ValidationException::fromResponse(
        ['message' => 'From body', 'errors' => []],
        422,
        'Explicit message',
    );

    expect($exception->getMessage())->toBe('Explicit message');
});

it('creates a validation exception from local errors', function (): void {
    $exception = ValidationException::fromErrors(['invoice' => 'Required'], 'Invalid order.');

    expect($exception->getMessage())->toBe('Invalid order.')
        ->and($exception->errors)->toBe(['invoice' => 'Required'])
        ->and($exception->getCode())->toBe(0);
});

it('uses a default message for local errors', function (): void {
    $exception = ValidationException::fromErrors(['invoice' => 'Required']);

    expect($exception->getMessage())->toBe('The supplied data is invalid.');
});

it('carries status code, response body and previous exception', function (): void {
    $previous = new RuntimeException('previous');
    $exception = new RequestFailedException('Failed', 503, '{"detail":"unavailable"}', $previous);

    expect($exception->getMessage())->toBe('Failed')
        ->and($exception->getCode())->toBe(503)
        ->and($exception->statusCode)->toBe(503)
        ->and($exception->responseBody)->toBe('{"detail":"unavailable"}')
        ->and($exception->getPrevious())->toBe($previous);
});

it('uses a zero code when no status is available', function (): void {
    $exception = new RequestFailedException('Failed');

    expect($exception->getCode())->toBe(0)
        ->and($exception->statusCode)->toBeNull()
        ->and($exception->responseBody)->toBeNull()
        ->and($exception->getPrevious())->toBeNull();
});

it('keeps the HTTP status on an authentication exception', function (): void {
    $exception = new AuthenticationException('Unauthenticated', 401);

    expect($exception->getMessage())->toBe('Unauthenticated')
        ->and($exception->getCode())->toBe(401)
        ->and($exception)->toBeInstanceOf(SteadFastException::class);
});

it('keeps the previous exception on an invalid webhook exception', function (): void {
    $previous = new RuntimeException('parse failure');
    $exception = new InvalidWebhookException('Invalid webhook payload.', 0, $previous);

    expect($exception->getMessage())->toBe('Invalid webhook payload.')
        ->and($exception->getPrevious())->toBe($previous);
});
