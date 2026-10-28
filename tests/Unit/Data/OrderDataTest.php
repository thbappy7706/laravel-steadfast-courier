<?php

declare(strict_types=1);

use Thbappy7706\SteadFastCourier\Data\OrderData;
use Thbappy7706\SteadFastCourier\Enums\DeliveryType;
use Thbappy7706\SteadFastCourier\Exceptions\ValidationException;

it('creates an order with all optional values', function (): void {
    $order = new OrderData(
        invoice: 'INV-1001',
        recipientName: 'John Doe',
        recipientPhone: '01712345678',
        recipientAddress: 'House 17, Road 3/A, Dhanmondi, Dhaka-1209',
        codAmount: 150.5,
        alternativePhone: '01812345678',
        recipientEmail: 'john@example.test',
        note: 'Handle with care',
        itemDescription: 'Two books',
        totalLot: 3,
        deliveryType: DeliveryType::PointDelivery,
    );

    expect($order->invoice)->toBe('INV-1001')
        ->and($order->recipientName)->toBe('John Doe')
        ->and($order->recipientPhone)->toBe('01712345678')
        ->and($order->recipientAddress)->toBe('House 17, Road 3/A, Dhanmondi, Dhaka-1209')
        ->and($order->codAmount)->toBe(150.5)
        ->and($order->alternativePhone)->toBe('01812345678')
        ->and($order->recipientEmail)->toBe('john@example.test')
        ->and($order->note)->toBe('Handle with care')
        ->and($order->itemDescription)->toBe('Two books')
        ->and($order->totalLot)->toBe(3)
        ->and($order->deliveryType)->toBe(DeliveryType::PointDelivery);
});

it('applies the documented defaults', function (): void {
    $order = new OrderData(
        invoice: 'INV-1001',
        recipientName: 'John Doe',
        recipientPhone: '01712345678',
        recipientAddress: 'Dhaka',
        codAmount: 0,
    );

    expect($order->alternativePhone)->toBeNull()
        ->and($order->recipientEmail)->toBeNull()
        ->and($order->note)->toBeNull()
        ->and($order->itemDescription)->toBeNull()
        ->and($order->totalLot)->toBe(1)
        ->and($order->deliveryType)->toBe(DeliveryType::HomeDelivery);
});

it('converts the order to an array including nulls', function (): void {
    $order = new OrderData(
        invoice: 'INV-1001',
        recipientName: 'John Doe',
        recipientPhone: '01712345678',
        recipientAddress: 'Dhaka',
        codAmount: 1000,
    );

    expect($order->toArray())->toBe([
        'invoice' => 'INV-1001',
        'recipient_name' => 'John Doe',
        'recipient_phone' => '01712345678',
        'recipient_address' => 'Dhaka',
        'cod_amount' => 1000,
        'alternative_phone' => null,
        'recipient_email' => null,
        'note' => null,
        'item_description' => null,
        'total_lot' => 1,
        'delivery_type' => 0,
    ]);
});

it('converts the order to a payload without null values', function (): void {
    $order = new OrderData(
        invoice: 'INV-1001',
        recipientName: 'John Doe',
        recipientPhone: '01712345678',
        recipientAddress: 'Dhaka',
        codAmount: 0,
        note: 'Ring the bell',
    );

    expect($order->toPayload())->toBe([
        'invoice' => 'INV-1001',
        'recipient_name' => 'John Doe',
        'recipient_phone' => '01712345678',
        'recipient_address' => 'Dhaka',
        'cod_amount' => 0,
        'note' => 'Ring the bell',
        'total_lot' => 1,
        'delivery_type' => 0,
    ]);
});

it('builds an order from an array with numeric strings and defaults', function (): void {
    $order = OrderData::fromArray(steadfastOrderPayload([
        'cod_amount' => '1000.50',
        'total_lot' => '2',
        'delivery_type' => '1',
        'alternative_phone' => '01812345678',
        'recipient_email' => 'john@example.test',
        'note' => 'Leave at the door',
        'item_description' => 'Shoes',
    ]));

    expect($order->invoice)->toBe('INV-1001')
        ->and($order->codAmount)->toBe(1000.5)
        ->and($order->totalLot)->toBe(2)
        ->and($order->deliveryType)->toBe(DeliveryType::PointDelivery)
        ->and($order->alternativePhone)->toBe('01812345678')
        ->and($order->recipientEmail)->toBe('john@example.test')
        ->and($order->note)->toBe('Leave at the door')
        ->and($order->itemDescription)->toBe('Shoes');
});

it('accepts a DeliveryType enum in the array', function (): void {
    $order = OrderData::fromArray(steadfastOrderPayload([
        'delivery_type' => DeliveryType::PointDelivery,
    ]));

    expect($order->deliveryType)->toBe(DeliveryType::PointDelivery);
});

it('defaults the delivery type when it is absent or null', function (?int $value): void {
    $payload = steadfastOrderPayload();

    if ($value !== null) {
        $payload['delivery_type'] = $value;
    }

    $order = OrderData::fromArray($payload);

    expect($order->deliveryType)->toBe(DeliveryType::HomeDelivery);
})->with([
    'absent' => [null],
    'zero' => [0],
]);

it('defaults the total lot when it is absent', function (): void {
    $order = OrderData::fromArray(steadfastOrderPayload());

    expect($order->totalLot)->toBe(1);
});

it('normalises empty optional strings to null', function (): void {
    $order = OrderData::fromArray(steadfastOrderPayload([
        'alternative_phone' => '',
        'recipient_email' => '',
        'note' => '',
        'item_description' => '',
    ]));

    expect($order->alternativePhone)->toBeNull()
        ->and($order->recipientEmail)->toBeNull()
        ->and($order->note)->toBeNull()
        ->and($order->itemDescription)->toBeNull();
});

it('ignores keys it does not know about', function (): void {
    $order = OrderData::fromArray(steadfastOrderPayload([
        'delivery_charge' => 60,
        'unknown' => 'value',
    ]));

    expect($order->toPayload())->not->toHaveKey('delivery_charge')
        ->and($order->toPayload())->not->toHaveKey('unknown');
});

it('reports every missing required field from an array', function (string $field, string $message): void {
    $payload = steadfastOrderPayload();
    unset($payload[$field]);

    $exception = steadfastValidationException(fn () => OrderData::fromArray($payload));

    expect($exception->getMessage())->toBe('The order data is invalid.')
        ->and($exception->errors)->toHaveKey($field)
        ->and($exception->errors[$field])->toBe($message);
})->with([
    'invoice' => ['invoice', 'The invoice field is required.'],
    'recipient name' => ['recipient_name', 'The recipient_name field is required.'],
    'recipient phone' => ['recipient_phone', 'The recipient_phone field is required.'],
    'recipient address' => ['recipient_address', 'The recipient_address field is required.'],
    'cod amount' => ['cod_amount', 'The cod_amount field is required.'],
]);

it('treats a null required field as missing', function (string $field): void {
    $payload = steadfastOrderPayload([$field => null]);

    $exception = steadfastValidationException(fn () => OrderData::fromArray($payload));

    expect($exception->errors)->toHaveKey($field);
})->with([
    'invoice' => 'invoice',
    'recipient name' => 'recipient_name',
    'recipient phone' => 'recipient_phone',
    'recipient address' => 'recipient_address',
    'cod amount' => 'cod_amount',
]);

it('rejects a non numeric cash-on-delivery amount in an array', function (): void {
    $exception = steadfastValidationException(
        fn () => OrderData::fromArray(steadfastOrderPayload(['cod_amount' => 'not-a-number'])),
    );

    expect($exception->errors)->toHaveKey('cod_amount')
        ->and($exception->errors['cod_amount'])->toBe('The cash-on-delivery amount must be numeric.');
});

it('rejects a non numeric total lot in an array', function (): void {
    $exception = steadfastValidationException(
        fn () => OrderData::fromArray(steadfastOrderPayload(['total_lot' => 'many'])),
    );

    expect($exception->errors)->toHaveKey('total_lot')
        ->and($exception->errors['total_lot'])->toBe('The total lot must be a number.');
});

it('rejects an unknown delivery type', function (mixed $value): void {
    $exception = steadfastValidationException(
        fn () => OrderData::fromArray(steadfastOrderPayload(['delivery_type' => $value])),
    );

    expect($exception->errors)->toHaveKey('delivery_type')
        ->and($exception->errors['delivery_type'])
        ->toBe('The delivery type must be 0 (home delivery) or 1 (point delivery).');
})->with([
    'two' => [2],
    'negative' => [-1],
    'string' => ['home'],
    'array' => [[]],
]);

it('rejects a non-string value for a required string field', function (): void {
    $exception = steadfastValidationException(
        fn () => OrderData::fromArray(steadfastOrderPayload(['invoice' => ['not-a-string']])),
    );

    expect($exception->errors)->toHaveKey('invoice')
        ->and($exception->errors['invoice'])->toBe('The invoice is required.');
});

it('validates the invoice format', function (string $invoice, string $message): void {
    $exception = steadfastValidationException(fn () => new OrderData(
        invoice: $invoice,
        recipientName: 'John Doe',
        recipientPhone: '01712345678',
        recipientAddress: 'Dhaka',
        codAmount: 100,
    ));

    expect($exception->getMessage())->toBe('The order data is invalid.')
        ->and($exception->errors)->toHaveKey('invoice')
        ->and($exception->errors['invoice'])->toBe($message);
})->with([
    'empty' => ['', 'The invoice is required.'],
    'whitespace only' => ['   ', 'The invoice is required.'],
    'space inside' => ['INV 1001', 'The invoice may only contain letters, numbers, hyphens and underscores.'],
    'slash' => ['INV/1001', 'The invoice may only contain letters, numbers, hyphens and underscores.'],
    'dot' => ['INV.1001', 'The invoice may only contain letters, numbers, hyphens and underscores.'],
    'too long' => [str_repeat('A', 101), 'The invoice may not be longer than 100 characters.'],
]);

it('accepts a 100 character invoice', function (): void {
    $order = new OrderData(
        invoice: str_repeat('A', 100),
        recipientName: 'John Doe',
        recipientPhone: '01712345678',
        recipientAddress: 'Dhaka',
        codAmount: 100,
    );

    expect($order->invoice)->toHaveLength(100);
});

it('accepts letters numbers hyphens and underscores in the invoice', function (): void {
    $order = new OrderData(
        invoice: 'INV_1001-A',
        recipientName: 'John Doe',
        recipientPhone: '01712345678',
        recipientAddress: 'Dhaka',
        codAmount: 100,
    );

    expect($order->invoice)->toBe('INV_1001-A');
});

it('validates the recipient name and address', function (array $overrides, string $field, string $message): void {
    $exception = steadfastValidationException(fn () => new OrderData(
        invoice: 'INV-1001',
        recipientName: $overrides['recipientName'] ?? 'John Doe',
        recipientPhone: '01712345678',
        recipientAddress: $overrides['recipientAddress'] ?? 'Dhaka',
        codAmount: 100,
    ));

    expect($exception->errors)->toHaveKey($field)
        ->and($exception->errors[$field])->toBe($message);
})->with([
    'empty name' => [['recipientName' => ''], 'recipient_name', 'The recipient name is required.'],
    'blank name' => [['recipientName' => '   '], 'recipient_name', 'The recipient name is required.'],
    'long name' => [['recipientName' => str_repeat('A', 101)], 'recipient_name', 'The recipient name may not be longer than 100 characters.'],
    'empty address' => [['recipientAddress' => ''], 'recipient_address', 'The recipient address is required.'],
    'blank address' => [['recipientAddress' => '   '], 'recipient_address', 'The recipient address is required.'],
    'long address' => [['recipientAddress' => str_repeat('A', 251)], 'recipient_address', 'The recipient address may not be longer than 250 characters.'],
]);

it('accepts the maximum name and address lengths', function (): void {
    $order = new OrderData(
        invoice: 'INV-1001',
        recipientName: str_repeat('A', 100),
        recipientPhone: '01712345678',
        recipientAddress: str_repeat('A', 250),
        codAmount: 100,
    );

    expect($order->recipientName)->toHaveLength(100)
        ->and($order->recipientAddress)->toHaveLength(250);
});

it('validates Bangladeshi mobile numbers', function (string $phone, bool $valid): void {
    $callback = fn () => new OrderData(
        invoice: 'INV-1001',
        recipientName: 'John Doe',
        recipientPhone: $phone,
        recipientAddress: 'Dhaka',
        codAmount: 100,
    );

    if ($valid) {
        expect($callback())->toBeInstanceOf(OrderData::class);

        return;
    }

    $exception = steadfastValidationException($callback);

    expect($exception->errors)->toHaveKey('recipient_phone')
        ->and($exception->errors['recipient_phone'])
        ->toBe('The recipient phone must be an 11-digit Bangladeshi mobile number.');
})->with([
    'valid gp' => ['01712345678', true],
    'valid robi' => ['01812345678', true],
    'valid banglalink' => ['01912345678', true],
    'too short' => ['0171234567', false],
    'too long' => ['017123456789', false],
    'does not start with 01' => ['02712345678', false],
    'contains letters' => ['0171234567a', false],
    'contains space' => ['017 12345678', false],
    'empty' => ['', false],
    'plus prefix' => ['+8801712345678', false],
]);

it('rejects a negative cash-on-delivery amount', function (): void {
    $exception = steadfastValidationException(fn () => new OrderData(
        invoice: 'INV-1001',
        recipientName: 'John Doe',
        recipientPhone: '01712345678',
        recipientAddress: 'Dhaka',
        codAmount: -0.01,
    ));

    expect($exception->errors)->toHaveKey('cod_amount')
        ->and($exception->errors['cod_amount'])->toBe('The cash-on-delivery amount must be zero or greater.');
});

it('accepts a zero cash-on-delivery amount', function (): void {
    $order = new OrderData(
        invoice: 'INV-1001',
        recipientName: 'John Doe',
        recipientPhone: '01712345678',
        recipientAddress: 'Dhaka',
        codAmount: 0,
    );

    expect($order->codAmount)->toBe(0);
});

it('validates the alternative phone, email and total lot', function (array $overrides, string $field, string $message): void {
    $exception = steadfastValidationException(fn () => new OrderData(
        invoice: 'INV-1001',
        recipientName: 'John Doe',
        recipientPhone: '01712345678',
        recipientAddress: 'Dhaka',
        codAmount: 100,
        alternativePhone: $overrides['alternativePhone'] ?? null,
        recipientEmail: $overrides['recipientEmail'] ?? null,
        totalLot: $overrides['totalLot'] ?? 1,
    ));

    expect($exception->errors)->toHaveKey($field)
        ->and($exception->errors[$field])->toBe($message);
})->with([
    'alternative phone' => [
        ['alternativePhone' => '12345'],
        'alternative_phone',
        'The alternative phone must be an 11-digit Bangladeshi mobile number.',
    ],
    'email' => [
        ['recipientEmail' => 'not-an-email'],
        'recipient_email',
        'The recipient email must be a valid email address.',
    ],
    'total lot' => [
        ['totalLot' => 0],
        'total_lot',
        'The total lot must be at least 1.',
    ],
]);

it('accepts a null alternative phone and email', function (): void {
    $order = new OrderData(
        invoice: 'INV-1001',
        recipientName: 'John Doe',
        recipientPhone: '01712345678',
        recipientAddress: 'Dhaka',
        codAmount: 100,
        alternativePhone: null,
        recipientEmail: null,
    );

    expect($order->alternativePhone)->toBeNull()
        ->and($order->recipientEmail)->toBeNull();
});

it('collects multiple validation errors at once', function (): void {
    $exception = steadfastValidationException(fn () => new OrderData(
        invoice: '',
        recipientName: '',
        recipientPhone: '123',
        recipientAddress: '',
        codAmount: -5,
        alternativePhone: 'abc',
        recipientEmail: 'nope',
        totalLot: 0,
    ));

    expect($exception->errors)->toHaveKeys([
        'invoice',
        'recipient_name',
        'recipient_phone',
        'recipient_address',
        'cod_amount',
        'alternative_phone',
        'recipient_email',
        'total_lot',
    ]);
});

it('exposes validation errors as an array', function (): void {
    $exception = new ValidationException('Invalid', ['invoice' => 'Required']);

    expect($exception->errors)->toBe(['invoice' => 'Required'])
        ->and($exception->getCode())->toBe(0);
});
