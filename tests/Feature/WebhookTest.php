<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Thbappy7706\SteadFastCourier\Data\WebhookPayloadData;
use Thbappy7706\SteadFastCourier\Enums\DeliveryStatus;
use Thbappy7706\SteadFastCourier\Events\DeliveryStatusUpdated;
use Thbappy7706\SteadFastCourier\Exceptions\ConfigurationException;
use Thbappy7706\SteadFastCourier\Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    Event::fake([DeliveryStatusUpdated::class]);
});

it('accepts a valid webhook and dispatches the event', function (): void {
    $response = $this->withToken('test-bearer-token')->postJson(
        route('steadfast-courier.webhook'),
        steadfastWebhookPayload(),
    );

    $response->assertOk()->assertExactJson(['status' => 'success']);

    Event::assertDispatched(
        DeliveryStatusUpdated::class,
        function (DeliveryStatusUpdated $event): bool {
            $payload = $event->payload;

            return $payload instanceof WebhookPayloadData
                && $payload->consignmentId === 1424107
                && $payload->invoice === 'INV-1001'
                && $payload->status === DeliveryStatus::Delivered
                && $payload->codAmount === 1000.75
                && $payload->updatedAt->toIso8601String() === '2026-10-28T12:00:00+06:00'
                && $payload->notificationType === 'delivery_status';
        },
    );
});

it('tolerates additional fields in the payload', function (): void {
    $response = $this->withToken('test-bearer-token')->postJson(
        route('steadfast-courier.webhook'),
        steadfastWebhookPayload([
            'delivery_charge' => 60,
            'tracking_message' => 'Package delivered to the recipient.',
            'unknown_field' => ['nested' => true],
        ]),
    );

    $response->assertOk();

    Event::assertDispatched(DeliveryStatusUpdated::class);
});

it('maps an unknown status without failing the request', function (): void {
    $this->withToken('test-bearer-token')->postJson(
        route('steadfast-courier.webhook'),
        steadfastWebhookPayload(['status' => 'delivery_robot_dispatched']),
    )->assertOk();

    Event::assertDispatched(
        DeliveryStatusUpdated::class,
        fn (DeliveryStatusUpdated $event): bool => $event->payload->status === DeliveryStatus::Unknown,
    );
});

it('maps an integer consignment id', function (): void {
    $this->withToken('test-bearer-token')->postJson(
        route('steadfast-courier.webhook'),
        steadfastWebhookPayload(['consignment_id' => 987654]),
    )->assertOk();

    Event::assertDispatched(
        DeliveryStatusUpdated::class,
        fn (DeliveryStatusUpdated $event): bool => $event->payload->consignmentId === 987654,
    );
});

it('defaults a missing notification type to null', function (): void {
    $payload = steadfastWebhookPayload();
    unset($payload['notification_type']);

    $this->withToken('test-bearer-token')
        ->postJson(route('steadfast-courier.webhook'), $payload)
        ->assertOk();

    Event::assertDispatched(
        DeliveryStatusUpdated::class,
        fn (DeliveryStatusUpdated $event): bool => $event->payload->notificationType === null,
    );
});

it('rejects a request without a bearer token', function (): void {
    $response = $this->postJson(route('steadfast-courier.webhook'), steadfastWebhookPayload());

    $response->assertUnauthorized()
        ->assertExactJson(['error' => 'Unauthorized']);

    Event::assertNotDispatched(DeliveryStatusUpdated::class);
});

it('rejects a request with an invalid bearer token', function (): void {
    $response = $this->withToken('wrong-token')
        ->postJson(route('steadfast-courier.webhook'), steadfastWebhookPayload());

    $response->assertUnauthorized()
        ->assertExactJson(['error' => 'Unauthorized']);

    Event::assertNotDispatched(DeliveryStatusUpdated::class);
});

it('rejects a non-bearer authorization header', function (): void {
    $response = $this->postJson(
        route('steadfast-courier.webhook'),
        steadfastWebhookPayload(),
        ['Authorization' => 'Basic dGVzdDp0ZXN0'],
    );

    $response->assertUnauthorized();
});

it('rejects a token that only shares a prefix with the configured token', function (): void {
    $this->withToken('test-bearer-token-extra')
        ->postJson(route('steadfast-courier.webhook'), steadfastWebhookPayload())
        ->assertUnauthorized();
});

it('fails loudly when no webhook token is configured', function (): void {
    config()->set('steadfast-courier.webhook_bearer_token', null);

    $this->withoutExceptionHandling();

    expect(fn () => $this->withToken('anything')
        ->postJson(route('steadfast-courier.webhook'), steadfastWebhookPayload()))
        ->toThrow(ConfigurationException::class, 'The SteadFast webhook bearer token is not configured.');
});

it('validates required webhook fields', function (string $field): void {
    $payload = steadfastWebhookPayload();
    unset($payload[$field]);

    $this->withToken('test-bearer-token')
        ->postJson(route('steadfast-courier.webhook'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    Event::assertNotDispatched(DeliveryStatusUpdated::class);
})->with([
    'consignment id' => 'consignment_id',
    'invoice' => 'invoice',
    'status' => 'status',
    'cod amount' => 'cod_amount',
    'updated at' => 'updated_at',
]);

it('validates field types', function (array $overrides, string $field): void {
    $this->withToken('test-bearer-token')
        ->postJson(route('steadfast-courier.webhook'), steadfastWebhookPayload($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    Event::assertNotDispatched(DeliveryStatusUpdated::class);
})->with([
    'non-numeric consignment id' => [['consignment_id' => 'ABC-123'], 'consignment_id'],
    'non-numeric cod amount' => [['cod_amount' => 'free'], 'cod_amount'],
    'malformed date' => [['updated_at' => 'not-a-date'], 'updated_at'],
    'non-string invoice' => [['invoice' => ['INV-1']], 'invoice'],
    'non-string status' => [['status' => ['delivered']], 'status'],
]);

it('rejects an empty invoice', function (): void {
    $this->withToken('test-bearer-token')
        ->postJson(route('steadfast-courier.webhook'), steadfastWebhookPayload(['invoice' => '']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('invoice');
});

it('accepts numeric strings for the cash-on-delivery amount', function (): void {
    $this->withToken('test-bearer-token')
        ->postJson(route('steadfast-courier.webhook'), steadfastWebhookPayload(['cod_amount' => '1500.50']))
        ->assertOk();

    Event::assertDispatched(
        DeliveryStatusUpdated::class,
        fn (DeliveryStatusUpdated $event): bool => $event->payload->codAmount === 1500.5,
    );
});

it('requires a post request', function (): void {
    $this->withToken('test-bearer-token')
        ->getJson(route('steadfast-courier.webhook'))
        ->assertMethodNotAllowed();
});
