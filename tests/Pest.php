<?php

declare(strict_types=1);

use Thbappy7706\SteadFastCourier\Exceptions\ValidationException;

/**
 * @param  callable(): mixed  $callback
 */
function steadfastValidationException(callable $callback): ValidationException
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        return $exception;
    }

    throw new RuntimeException('Expected a ValidationException to be thrown.');
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function steadfastOrderPayload(array $overrides = []): array
{
    return array_merge([
        'invoice' => 'INV-1001',
        'recipient_name' => 'John Doe',
        'recipient_phone' => '01712345678',
        'recipient_address' => 'House 17, Road 3/A, Dhanmondi, Dhaka-1209',
        'cod_amount' => 1000,
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function steadfastConsignmentPayload(array $overrides = []): array
{
    return array_merge([
        'consignment_id' => 1424107,
        'invoice' => 'INV-1001',
        'tracking_code' => '15BAEB8A',
        'status' => 'in_review',
        'recipient_name' => 'John Doe',
        'recipient_phone' => '01712345678',
        'recipient_address' => 'House 17, Road 3/A, Dhanmondi, Dhaka-1209',
        'cod_amount' => 1000,
        'note' => 'Handle with care',
        'created_at' => '2026-10-28T10:00:00+06:00',
        'updated_at' => '2026-10-28T11:00:00+06:00',
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function steadfastWebhookPayload(array $overrides = []): array
{
    return array_merge([
        'consignment_id' => 1424107,
        'invoice' => 'INV-1001',
        'status' => 'delivered',
        'cod_amount' => 1000.75,
        'updated_at' => '2026-10-28T12:00:00+06:00',
        'notification_type' => 'delivery_status',
    ], $overrides);
}
