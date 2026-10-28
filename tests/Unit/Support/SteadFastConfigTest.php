<?php

declare(strict_types=1);

use Thbappy7706\SteadFastCourier\Exceptions\ConfigurationException;
use Thbappy7706\SteadFastCourier\Support\SteadFastConfig;

it('uses sensible defaults when no configuration is provided', function (): void {
    $config = SteadFastConfig::fromArray([]);

    expect($config->baseUrl)->toBe('https://portal.steadfast.com.bd/api/v1')
        ->and($config->apiKey)->toBeNull()
        ->and($config->secretKey)->toBeNull()
        ->and($config->webhookBearerToken)->toBeNull()
        ->and($config->timeout)->toBe(15)
        ->and($config->connectTimeout)->toBe(5)
        ->and($config->retryTimes)->toBe(2)
        ->and($config->retrySleepMs)->toBe(200)
        ->and($config->bulkChunkSize)->toBe(500)
        ->and($config->loggingEnabled)->toBeFalse()
        ->and($config->logChannel)->toBeNull();
});

it('hydrates every option from an array', function (): void {
    $config = SteadFastConfig::fromArray([
        'base_url' => 'https://example.test/api/v1/',
        'api_key' => '  key  ',
        'secret_key' => ' secret ',
        'webhook_bearer_token' => ' token ',
        'timeout' => '20',
        'connect_timeout' => 7,
        'retry' => [
            'times' => '3',
            'sleep_ms' => '100',
        ],
        'bulk_chunk_size' => 100,
        'logging' => [
            'enabled' => 'true',
            'channel' => 'single',
        ],
    ]);

    expect($config->baseUrl)->toBe('https://example.test/api/v1')
        ->and($config->apiKey)->toBe('key')
        ->and($config->secretKey)->toBe('secret')
        ->and($config->webhookBearerToken)->toBe('token')
        ->and($config->timeout)->toBe(20)
        ->and($config->connectTimeout)->toBe(7)
        ->and($config->retryTimes)->toBe(3)
        ->and($config->retrySleepMs)->toBe(100)
        ->and($config->bulkChunkSize)->toBe(100)
        ->and($config->loggingEnabled)->toBeTrue()
        ->and($config->logChannel)->toBe('single');
});

it('turns blank and non-string credentials into null', function (mixed $value): void {
    $config = SteadFastConfig::fromArray([
        'api_key' => $value,
        'secret_key' => $value,
        'webhook_bearer_token' => $value,
    ]);

    expect($config->apiKey)->toBeNull()
        ->and($config->secretKey)->toBeNull()
        ->and($config->webhookBearerToken)->toBeNull();
})->with([
    'empty string' => '',
    'whitespace' => '   ',
    'null' => null,
    'array' => [[]],
    'integer' => [123],
    'boolean' => [true],
]);

it('clamps numeric options to safe ranges', function (): void {
    $config = SteadFastConfig::fromArray([
        'timeout' => 0,
        'connect_timeout' => -10,
        'retry' => [
            'times' => -5,
            'sleep_ms' => -1,
        ],
        'bulk_chunk_size' => 0,
    ]);

    expect($config->timeout)->toBe(1)
        ->and($config->connectTimeout)->toBe(1)
        ->and($config->retryTimes)->toBe(0)
        ->and($config->retrySleepMs)->toBe(0)
        ->and($config->bulkChunkSize)->toBe(1);
});

it('caps the bulk chunk size at the API maximum', function (): void {
    $config = SteadFastConfig::fromArray(['bulk_chunk_size' => 501]);

    expect($config->bulkChunkSize)->toBe(SteadFastConfig::MAX_BULK_CHUNK_SIZE)
        ->and($config->bulkChunkSize)->toBe(500);
});

it('falls back to defaults for non numeric values', function (): void {
    $config = SteadFastConfig::fromArray([
        'base_url' => 123,
        'timeout' => 'not-a-number',
        'connect_timeout' => [],
        'retry' => 'nope',
        'bulk_chunk_size' => 'abc',
        'logging' => 'nope',
    ]);

    expect($config->baseUrl)->toBe(SteadFastConfig::DEFAULT_BASE_URL)
        ->and($config->timeout)->toBe(15)
        ->and($config->connectTimeout)->toBe(5)
        ->and($config->retryTimes)->toBe(2)
        ->and($config->retrySleepMs)->toBe(200)
        ->and($config->bulkChunkSize)->toBe(500)
        ->and($config->loggingEnabled)->toBeFalse();
});

it('casts logging flags leniently', function (mixed $value, bool $expected): void {
    $config = SteadFastConfig::fromArray([
        'logging' => ['enabled' => $value],
    ]);

    expect($config->loggingEnabled)->toBe($expected);
})->with([
    'true boolean' => [true, true],
    'false boolean' => [false, false],
    'one' => [1, true],
    'zero' => [0, false],
    'numeric string one' => ['1', true],
    'numeric string zero' => ['0', false],
    'word true' => ['true', true],
    'word false' => ['false', false],
    'yes' => ['yes', true],
    'no' => ['no', false],
    'on' => ['on', true],
    'off' => ['off', false],
    'invalid string' => ['nonsense', false],
    'null' => [null, false],
    'array' => [[], false],
]);

it('reports whether credentials are present', function (?string $apiKey, ?string $secretKey, bool $expected): void {
    $config = new SteadFastConfig(apiKey: $apiKey, secretKey: $secretKey);

    expect($config->hasCredentials())->toBe($expected);
})->with([
    'both set' => ['key', 'secret', true],
    'only api key' => ['key', null, false],
    'only secret key' => [null, 'secret', false],
    'none' => [null, null, false],
]);

it('throws a configuration exception when credentials are missing', function (): void {
    $config = new SteadFastConfig;

    expect(fn () => $config->assertHasCredentials())
        ->toThrow(ConfigurationException::class, 'SteadFast API credentials are not configured.');
});

it('does not throw when credentials are present', function (): void {
    $config = new SteadFastConfig(apiKey: 'key', secretKey: 'secret');

    $config->assertHasCredentials();

    expect($config->hasCredentials())->toBeTrue();
});

it('returns a new instance from withCredentials without mutating the original', function (): void {
    $original = SteadFastConfig::fromArray([
        'base_url' => 'https://original.test/api/v1',
        'api_key' => 'original-key',
        'secret_key' => 'original-secret',
        'webhook_bearer_token' => 'original-token',
        'timeout' => 3,
        'retry' => ['times' => 1, 'sleep_ms' => 50],
        'bulk_chunk_size' => 10,
        'logging' => ['enabled' => true, 'channel' => 'daily'],
    ]);

    $derived = $original->withCredentials(
        apiKey: 'new-key',
        secretKey: 'new-secret',
        baseUrl: 'https://tenant.test/api/v1/',
    );

    expect($derived)->not->toBe($original)
        ->and($derived->baseUrl)->toBe('https://tenant.test/api/v1')
        ->and($derived->apiKey)->toBe('new-key')
        ->and($derived->secretKey)->toBe('new-secret')
        ->and($derived->webhookBearerToken)->toBe('original-token')
        ->and($derived->timeout)->toBe(3)
        ->and($derived->retryTimes)->toBe(1)
        ->and($derived->retrySleepMs)->toBe(50)
        ->and($derived->bulkChunkSize)->toBe(10)
        ->and($derived->loggingEnabled)->toBeTrue()
        ->and($derived->logChannel)->toBe('daily')
        ->and($original->apiKey)->toBe('original-key')
        ->and($original->secretKey)->toBe('original-secret')
        ->and($original->baseUrl)->toBe('https://original.test/api/v1');
});

it('keeps the current base url when withCredentials receives a blank url', function (?string $baseUrl): void {
    $original = new SteadFastConfig(baseUrl: 'https://original.test/api/v1');

    $derived = $original->withCredentials('key', 'secret', $baseUrl);

    expect($derived->baseUrl)->toBe('https://original.test/api/v1');
})->with([
    'null' => [null],
    'empty' => [''],
    'whitespace' => ['   '],
]);
