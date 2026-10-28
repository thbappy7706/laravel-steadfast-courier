<?php

declare(strict_types=1);

namespace Thbappy7706\SteadFastCourier\Data;

use Thbappy7706\SteadFastCourier\Enums\DeliveryStatus;

final readonly class DeliveryStatusData
{
    public function __construct(
        public DeliveryStatus $status,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $status = $data['delivery_status'] ?? $data['status'] ?? null;

        return new self(
            DeliveryStatus::tryFromOrUnknown(is_string($status) ? $status : 'unknown'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'delivery_status' => $this->status->value,
        ];
    }
}
