<?php

declare(strict_types=1);

namespace Thbappy7706\SteadFastCourier\Data;

use Thbappy7706\SteadFastCourier\Exceptions\RequestFailedException;

final readonly class BalanceData
{
    public function __construct(
        public float $currentBalance,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws RequestFailedException
     */
    public static function fromArray(array $data): self
    {
        $balance = $data['current_balance'] ?? null;

        if (! is_numeric($balance)) {
            throw new RequestFailedException('The SteadFast API response did not contain a current balance.');
        }

        return new self((float) $balance);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'current_balance' => $this->currentBalance,
        ];
    }
}
