<?php

declare(strict_types=1);

namespace Thbappy7706\SteadFastCourier\Exceptions;

use Throwable;

final class RequestFailedException extends SteadFastException
{
    public function __construct(
        string $message,
        public readonly ?int $statusCode = null,
        public readonly ?string $responseBody = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode ?? 0, $previous);
    }
}
