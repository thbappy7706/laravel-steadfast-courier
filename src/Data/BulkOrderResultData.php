<?php

declare(strict_types=1);

namespace Thbappy7706\SteadFastCourier\Data;

final readonly class BulkOrderResultData
{
    public function __construct(
        public string $invoice,
        public bool $successful,
        public int|string|null $consignmentId = null,
        public ?string $trackingCode = null,
        public ?string $status = null,
        public ?string $error = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $invoice = $data['invoice'] ?? null;
        $status = $data['status'] ?? null;
        $consignmentId = $data['consignment_id'] ?? null;
        $trackingCode = $data['tracking_code'] ?? null;

        return new self(
            invoice: is_string($invoice) ? $invoice : (is_int($invoice) ? (string) $invoice : ''),
            successful: $status === 'success' || ($data['successful'] ?? null) === true,
            consignmentId: is_int($consignmentId) || is_string($consignmentId) ? $consignmentId : null,
            trackingCode: is_string($trackingCode) ? $trackingCode : null,
            status: is_string($status) ? $status : null,
            error: self::errorFrom($data),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'invoice' => $this->invoice,
            'successful' => $this->successful,
            'consignment_id' => $this->consignmentId,
            'tracking_code' => $this->trackingCode,
            'status' => $this->status,
            'error' => $this->error,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function errorFrom(array $data): ?string
    {
        $error = $data['error'] ?? $data['message'] ?? null;

        if (is_string($error) && $error !== '') {
            return $error;
        }

        $errors = $data['errors'] ?? null;

        if (is_string($errors) && $errors !== '') {
            return $errors;
        }

        if (is_array($errors)) {
            $messages = array_filter($errors, 'is_string');

            return $messages === [] ? null : implode(' ', $messages);
        }

        return null;
    }
}
