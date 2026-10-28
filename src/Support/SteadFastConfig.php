<?php

declare(strict_types=1);

namespace Thbappy7706\SteadFastCourier\Support;

use Thbappy7706\SteadFastCourier\Exceptions\ConfigurationException;

final readonly class SteadFastConfig
{
    public const DEFAULT_BASE_URL = 'https://portal.steadfast.com.bd/api/v1';

    public const MAX_BULK_CHUNK_SIZE = 500;

    public function __construct(
        public string $baseUrl = self::DEFAULT_BASE_URL,
        public ?string $apiKey = null,
        public ?string $secretKey = null,
        public ?string $webhookBearerToken = null,
        public int $timeout = 15,
        public int $connectTimeout = 5,
        public int $retryTimes = 2,
        public int $retrySleepMs = 200,
        public int $bulkChunkSize = self::MAX_BULK_CHUNK_SIZE,
        public bool $loggingEnabled = false,
        public ?string $logChannel = null,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $retry = is_array($config['retry'] ?? null) ? $config['retry'] : [];
        $webhook = is_array($config['webhook'] ?? null) ? $config['webhook'] : [];
        $logging = is_array($config['logging'] ?? null) ? $config['logging'] : [];

        return new self(
            baseUrl: rtrim(self::stringOr($config['base_url'] ?? null, self::DEFAULT_BASE_URL), '/'),
            apiKey: self::nullableString($config['api_key'] ?? null),
            secretKey: self::nullableString($config['secret_key'] ?? null),
            webhookBearerToken: self::nullableString($config['webhook_bearer_token'] ?? null),
            timeout: max(1, self::intOr($config['timeout'] ?? null, 15)),
            connectTimeout: max(1, self::intOr($config['connect_timeout'] ?? null, 5)),
            retryTimes: max(0, self::intOr($retry['times'] ?? null, 2)),
            retrySleepMs: max(0, self::intOr($retry['sleep_ms'] ?? null, 200)),
            bulkChunkSize: min(self::MAX_BULK_CHUNK_SIZE, max(1, self::intOr($config['bulk_chunk_size'] ?? null, self::MAX_BULK_CHUNK_SIZE))),
            loggingEnabled: self::boolOr($logging['enabled'] ?? null, false),
            logChannel: self::nullableString($logging['channel'] ?? null),
        );
    }

    public function hasCredentials(): bool
    {
        return $this->apiKey !== null && $this->secretKey !== null;
    }

    /**
     * @throws ConfigurationException
     */
    public function assertHasCredentials(): void
    {
        if (! $this->hasCredentials()) {
            throw ConfigurationException::missingCredentials();
        }
    }

    public function withCredentials(string $apiKey, string $secretKey, ?string $baseUrl = null): self
    {
        return new self(
            baseUrl: $baseUrl !== null && trim($baseUrl) !== ''
                ? rtrim($baseUrl, '/')
                : $this->baseUrl,
            apiKey: $apiKey,
            secretKey: $secretKey,
            webhookBearerToken: $this->webhookBearerToken,
            timeout: $this->timeout,
            connectTimeout: $this->connectTimeout,
            retryTimes: $this->retryTimes,
            retrySleepMs: $this->retrySleepMs,
            bulkChunkSize: $this->bulkChunkSize,
            loggingEnabled: $this->loggingEnabled,
            logChannel: $this->logChannel,
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private static function stringOr(mixed $value, string $default): string
    {
        return self::nullableString($value) ?? $default;
    }

    private static function intOr(mixed $value, int $default): int
    {
        return is_numeric($value) ? (int) $value : $default;
    }

    private static function boolOr(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (bool) $value;
        }

        if (is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
        }

        return $default;
    }
}
