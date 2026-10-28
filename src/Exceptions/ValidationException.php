<?php

declare(strict_types=1);

namespace Thbappy7706\SteadFastCourier\Exceptions;

final class ValidationException extends SteadFastException
{
    /**
     * @param  array<array-key, mixed>  $errors
     */
    public function __construct(
        string $message,
        public readonly array $errors = [],
        int $code = 0,
    ) {
        parent::__construct($message, $code);
    }

    /**
     * @param  array<array-key, mixed>  $body
     */
    public static function fromResponse(array $body, int $status, ?string $message = null): self
    {
        $errors = $body['errors'] ?? [];
        $errors = is_array($errors) ? $errors : [];

        $message ??= is_string($body['message'] ?? null)
            ? $body['message']
            : 'The SteadFast API rejected the request.';

        return new self($message, $errors, $status);
    }

    /**
     * @param  array<string, string>  $errors
     */
    public static function fromErrors(array $errors, string $message = 'The supplied data is invalid.'): self
    {
        return new self($message, $errors);
    }
}
