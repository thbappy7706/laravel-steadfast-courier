<?php

declare(strict_types=1);

use Thbappy7706\SteadFastCourier\Data\BalanceData;
use Thbappy7706\SteadFastCourier\Exceptions\RequestFailedException;

it('maps a numeric balance', function (mixed $value, float $expected): void {
    $balance = BalanceData::fromArray(['current_balance' => $value]);

    expect($balance->currentBalance)->toBe($expected);
})->with([
    'float' => [1250.75, 1250.75],
    'integer' => [1000, 1000.0],
    'numeric string' => ['1250.75', 1250.75],
    'zero' => [0, 0.0],
]);

it('throws when the balance is missing', function (): void {
    expect(fn () => BalanceData::fromArray([]))
        ->toThrow(
            RequestFailedException::class,
            'The SteadFast API response did not contain a current balance.',
        );
});

it('throws when the balance is not numeric', function (mixed $value): void {
    expect(fn () => BalanceData::fromArray(['current_balance' => $value]))
        ->toThrow(RequestFailedException::class);
})->with([
    'null' => [null],
    'string' => ['unknown'],
    'array' => [[]],
    'boolean' => [true],
]);

it('converts the balance to an array', function (): void {
    $balance = new BalanceData(1250.75);

    expect($balance->toArray())->toBe(['current_balance' => 1250.75]);
});
