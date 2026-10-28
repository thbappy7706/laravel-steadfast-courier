<?php

declare(strict_types=1);

namespace Thbappy7706\SteadFastCourier\Data;

use Carbon\CarbonImmutable;
use Thbappy7706\SteadFastCourier\Enums\DeliveryStatus;
use Thbappy7706\SteadFastCourier\Exceptions\InvalidWebhookException;
use Throwable;

final readonly class WebhookPayloadData
{
    public function __construct(
        public int|string $consignmentId,
        public string $invoice,
        public DeliveryStatus $status,
        public float $codAmount,
        public CarbonImmutable $updatedAt,
        public ?string $notificationType = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidWebhookException
     */
    public static function fromArray(array $data): self
    {
        $consignmentId = $data['consignment_id'] ?? null;
        $invoice = $data['invoice'] ?? null;
        $status = $data['status'] ?? null;
        $codAmount = $data['cod_amount'] ?? null;
        $updatedAt = $data['updated_at'] ?? null;
        $notificationType = $data['notification_type'] ?? null;

        if (! is_int($consignmentId) && ! is_string($consignmentId)) {
            throw new InvalidWebhookException('The webhook payload does not contain a valid consignment ID.');
        }

        if (! is_string($invoice) || $invoice === '') {
            throw new InvalidWebhookException('The webhook payload does not contain a valid invoice.');
        }

        if (! is_string($status) || $status === '') {
            throw new InvalidWebhookException('The webhook payload does not contain a valid status.');
        }

        if (! is_numeric($codAmount)) {
            throw new InvalidWebhookException('The webhook payload does not contain a valid cash-on-delivery amount.');
        }

        if (! is_string($updatedAt)) {
            throw new InvalidWebhookException('The webhook payload does not contain a valid updated-at timestamp.');
        }

        try {
            $date = CarbonImmutable::parse($updatedAt);
        } catch (Throwable $exception) {
            throw new InvalidWebhookException('The webhook payload contains an invalid updated-at timestamp.', 0, $exception);
        }

        return new self(
            consignmentId: $consignmentId,
            invoice: $invoice,
            status: DeliveryStatus::tryFromOrUnknown($status),
            codAmount: (float) $codAmount,
            updatedAt: $date,
            notificationType: is_string($notificationType) ? $notificationType : null,
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
            'status' => $this->status->value,
            'cod_amount' => $this->codAmount,
            'updated_at' => $this->updatedAt->toIso8601String(),
            'notification_type' => $this->notificationType,
        ];
    }
}
