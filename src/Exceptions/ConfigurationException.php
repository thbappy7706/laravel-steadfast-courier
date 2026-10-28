<?php

declare(strict_types=1);

namespace Thbappy7706\SteadFastCourier\Exceptions;

final class ConfigurationException extends SteadFastException
{
    public static function missingCredentials(): self
    {
        return new self('SteadFast API credentials are not configured. Set STEADFAST_API_KEY and STEADFAST_SECRET_KEY.');
    }

    public static function missingWebhookToken(): self
    {
        return new self('The SteadFast webhook bearer token is not configured. Set STEADFAST_BEARER_TOKEN.');
    }
}
