<?php

declare(strict_types=1);

namespace Thbappy7706\SteadFastCourier\Data;

use Thbappy7706\SteadFastCourier\Enums\DeliveryType;
use Thbappy7706\SteadFastCourier\Exceptions\ValidationException;

final readonly class OrderData
{
    public function __construct(
        public string $invoice,
        public string $recipientName,
        public string $recipientPhone,
        public string $recipientAddress,
        public int|float $codAmount,
        public ?string $alternativePhone = null,
        public ?string $recipientEmail = null,
        public ?string $note = null,
        public ?string $itemDescription = null,
        public int $totalLot = 1,
        public DeliveryType $deliveryType = DeliveryType::HomeDelivery,
    ) {
        $this->validate();
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public static function fromArray(array $data): self
    {
        $errors = [];

        foreach (['invoice', 'recipient_name', 'recipient_phone', 'recipient_address', 'cod_amount'] as $key) {
            if (! array_key_exists($key, $data) || $data[$key] === null) {
                $errors[$key] = 'The '.$key.' field is required.';
            }
        }

        if ($errors === [] && ! is_numeric($data['cod_amount'])) {
            $errors['cod_amount'] = 'The cash-on-delivery amount must be numeric.';
        }

        if (array_key_exists('total_lot', $data) && ! is_numeric($data['total_lot'])) {
            $errors['total_lot'] = 'The total lot must be a number.';
        }

        if ($errors !== []) {
            throw ValidationException::fromErrors($errors, 'The order data is invalid.');
        }

        $deliveryType = $data['delivery_type'] ?? DeliveryType::HomeDelivery;

        if ($deliveryType instanceof DeliveryType) {
            $type = $deliveryType;
        } elseif (is_numeric($deliveryType) && ($type = DeliveryType::tryFrom((int) $deliveryType)) !== null) {
            // Resolved above.
        } else {
            throw ValidationException::fromErrors([
                'delivery_type' => 'The delivery type must be 0 (home delivery) or 1 (point delivery).',
            ]);
        }

        /** @var int|float $codAmount */
        $codAmount = $data['cod_amount'];

        /** @var int|float $totalLot */
        $totalLot = $data['total_lot'] ?? 1;

        return new self(
            invoice: self::stringValue($data, 'invoice'),
            recipientName: self::stringValue($data, 'recipient_name'),
            recipientPhone: self::stringValue($data, 'recipient_phone'),
            recipientAddress: self::stringValue($data, 'recipient_address'),
            codAmount: $codAmount + 0,
            alternativePhone: self::nullableStringValue($data, 'alternative_phone'),
            recipientEmail: self::nullableStringValue($data, 'recipient_email'),
            note: self::nullableStringValue($data, 'note'),
            itemDescription: self::nullableStringValue($data, 'item_description'),
            totalLot: (int) $totalLot,
            deliveryType: $type,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'invoice' => $this->invoice,
            'recipient_name' => $this->recipientName,
            'recipient_phone' => $this->recipientPhone,
            'recipient_address' => $this->recipientAddress,
            'cod_amount' => $this->codAmount,
            'alternative_phone' => $this->alternativePhone,
            'recipient_email' => $this->recipientEmail,
            'note' => $this->note,
            'item_description' => $this->itemDescription,
            'total_lot' => $this->totalLot,
            'delivery_type' => $this->deliveryType->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return array_filter(
            $this->toArray(),
            static fn (mixed $value): bool => $value !== null,
        );
    }

    /**
     * @throws ValidationException
     */
    private function validate(): void
    {
        $errors = [];

        $invoice = trim($this->invoice);
        if ($invoice === '') {
            $errors['invoice'] = 'The invoice is required.';
        } elseif (preg_match('/^[A-Za-z0-9_-]+$/', $invoice) !== 1) {
            $errors['invoice'] = 'The invoice may only contain letters, numbers, hyphens and underscores.';
        } elseif (mb_strlen($invoice) > 100) {
            $errors['invoice'] = 'The invoice may not be longer than 100 characters.';
        }

        $name = trim($this->recipientName);
        if ($name === '') {
            $errors['recipient_name'] = 'The recipient name is required.';
        } elseif (mb_strlen($name) > 100) {
            $errors['recipient_name'] = 'The recipient name may not be longer than 100 characters.';
        }

        if (! self::isBangladeshiMobile($this->recipientPhone)) {
            $errors['recipient_phone'] = 'The recipient phone must be an 11-digit Bangladeshi mobile number.';
        }

        $address = trim($this->recipientAddress);
        if ($address === '') {
            $errors['recipient_address'] = 'The recipient address is required.';
        } elseif (mb_strlen($address) > 250) {
            $errors['recipient_address'] = 'The recipient address may not be longer than 250 characters.';
        }

        if ($this->codAmount < 0) {
            $errors['cod_amount'] = 'The cash-on-delivery amount must be zero or greater.';
        }

        if ($this->alternativePhone !== null && ! self::isBangladeshiMobile($this->alternativePhone)) {
            $errors['alternative_phone'] = 'The alternative phone must be an 11-digit Bangladeshi mobile number.';
        }

        if ($this->recipientEmail !== null && filter_var($this->recipientEmail, FILTER_VALIDATE_EMAIL) === false) {
            $errors['recipient_email'] = 'The recipient email must be a valid email address.';
        }

        if ($this->totalLot < 1) {
            $errors['total_lot'] = 'The total lot must be at least 1.';
        }

        if ($errors !== []) {
            throw ValidationException::fromErrors($errors, 'The order data is invalid.');
        }
    }

    private static function isBangladeshiMobile(string $phone): bool
    {
        return preg_match('/^01\d{9}$/', $phone) === 1;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function stringValue(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : '';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function nullableStringValue(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if (! is_string($value)) {
            return null;
        }

        return $value === '' ? null : $value;
    }
}
