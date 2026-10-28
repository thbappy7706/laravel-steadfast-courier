<?php

declare(strict_types=1);

namespace Thbappy7706\SteadFastCourier\Data;

use Carbon\CarbonImmutable;
use Thbappy7706\SteadFastCourier\Enums\DeliveryStatus;
use Thbappy7706\SteadFastCourier\Exceptions\RequestFailedException;
use Throwable;

final readonly class ConsignmentData
{
    public function __construct(
        public int|string $consignmentId,
        public string $invoice,
        public string $trackingCode,
        public DeliveryStatus $status,
        public ?string $recipientName = null,
        public ?string $recipientPhone = null,
        public ?string $recipientAddress = null,
        public int|float|null $codAmount = null,
        public ?string $note = null,
        public ?CarbonImmutable $createdAt = null,
        public ?CarbonImmutable $updatedAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws RequestFailedException
     */
    public static function fromArray(array $data): self
    {
        if (is_array($data['consignment'] ?? null)) {
            $data = $data['consignment'];
        }

        $consignmentId = $data['consignment_id'] ?? null;

        if (! is_int($consignmentId) && ! is_string($consignmentId)) {
            throw new RequestFailedException('The SteadFast API response did not contain a consignment ID.');
        }

        $trackingCode = $data['tracking_code'] ?? null;

        if (! is_string($trackingCode)) {
            throw new RequestFailedException('The SteadFast API response did not contain a tracking code.');
        }

        $codAmount = $data['cod_amount'] ?? null;

        return new self(
            consignmentId: $consignmentId,
            invoice: self::stringOrNull($data['invoice'] ?? null) ?? '',
            trackingCode: $trackingCode,
            status: DeliveryStatus::tryFromOrUnknown(self::stringOrNull($data['status'] ?? null) ?? 'unknown'),
            recipientName: self::stringOrNull($data['recipient_name'] ?? null),
            recipientPhone: self::stringOrNull($data['recipient_phone'] ?? null),
            recipientAddress: self::stringOrNull($data['recipient_address'] ?? null),
            codAmount: is_numeric($codAmount) ? $codAmount + 0 : null,
            note: self::stringOrNull($data['note'] ?? null),
            createdAt: self::dateOrNull($data['created_at'] ?? null),
            updatedAt: self::dateOrNull($data['updated_at'] ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'consignment_id' => $this->consignmentId,
            'invoice' => $this->invoice,
            'tracking_code' => $this->trackingCode,
            'status' => $this->status->value,
            'recipient_name' => $this->recipientName,
            'recipient_phone' => $this->recipientPhone,
            'recipient_address' => $this->recipientAddress,
            'cod_amount' => $this->codAmount,
            'note' => $this->note,
            'created_at' => $this->createdAt?->toIso8601String(),
            'updated_at' => $this->updatedAt?->toIso8601String(),
        ];
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private static function dateOrNull(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
