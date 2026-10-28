# Laravel SteadFast Courier
 

A modern Laravel 13 integration for the [SteadFast Courier](https://steadfast.com.bd) merchant API. Place orders, create consignments in bulk, track delivery status, read your current balance and receive webhook callbacks — with typed DTOs, enums, immutable configuration and Laravel's HTTP client.

## Requirements

- **PHP 8.3 or newer**
- **Laravel 13**
- A SteadFast merchant account with API credentials

## Installation

```bash
composer require thbappy7706/laravel-steadfast-courier
```

The service provider and the `SteadFastCourier` facade alias are registered automatically. Publish the configuration file:

```bash
php artisan vendor:publish --tag="steadfast-courier-config"
```

## Configuration

Add your credentials to `.env`:

```env
STEADFAST_BASE_URL="https://portal.steadfast.com.bd/api/v1"
STEADFAST_API_KEY="your-api-key"
STEADFAST_SECRET_KEY="your-secret-key"

# Optional
STEADFAST_BEARER_TOKEN="your-webhook-token"
STEADFAST_WEBHOOK_ENABLED=false
STEADFAST_WEBHOOK_PATH="steadfast/webhook"
STEADFAST_TIMEOUT=15
STEADFAST_CONNECT_TIMEOUT=5
STEADFAST_RETRY_TIMES=2
STEADFAST_RETRY_SLEEP_MS=200
STEADFAST_LOGGING=false
STEADFAST_LOG_CHANNEL=stack
```

There are no placeholder credentials in the defaults. If a request is attempted without an API key and secret key, a `ConfigurationException` is thrown immediately.

## Usage

### Place an order

```php
use Thbappy7706\SteadFastCourier\Data\OrderData;
use Thbappy7706\SteadFastCourier\Facades\SteadFastCourier;

$consignment = SteadFastCourier::placeOrder(new OrderData(
    invoice: 'INV-1001',
    recipientName: 'John Doe',
    recipientPhone: '01712345678',
    recipientAddress: 'House 17, Road 3/A, Dhanmondi, Dhaka-1209',
    codAmount: 1000,
    note: 'Handle with care',
));

$consignment->consignmentId;  // 1424107
$consignment->trackingCode;   // "15BAEB8A"
$consignment->status;         // DeliveryStatus::InReview
$consignment->status->label(); // "In review"
```

The order is validated before it is sent: invoice format, 11-digit Bangladeshi phone numbers, address length, non-negative COD amount, optional email, item count and delivery type.

A plain array is accepted too, which makes migration from older packages easy:

```php
$consignment = SteadFastCourier::placeOrder([
    'invoice' => 'INV-1001',
    'recipient_name' => 'John Doe',
    'recipient_phone' => '01712345678',
    'recipient_address' => 'House 17, Road 3/A, Dhanmondi, Dhaka-1209',
    'cod_amount' => 1000,
    'delivery_type' => 0, // 0 = home delivery, 1 = point delivery
]);
```

### Create orders in bulk

```php
$results = SteadFastCourier::bulkCreateOrders([
    [
        'invoice' => 'INV-1001',
        'recipient_name' => 'John Doe',
        'recipient_phone' => '01712345678',
        'recipient_address' => 'House 17, Road 3/A, Dhanmondi, Dhaka-1209',
        'cod_amount' => 1000,
    ],
    new OrderData(
        invoice: 'INV-1002',
        recipientName: 'Jane Smith',
        recipientPhone: '01812345678',
        recipientAddress: 'House 44, Road 2, Uttara, Dhaka',
        codAmount: 1500,
    ),
]);

foreach ($results as $result) {
    if ($result->successful) {
        // $result->invoice, $result->consignmentId, $result->trackingCode
    } else {
        // $result->error explains why this order failed
    }
}
```

- Requests are automatically chunked to 500 orders per API call.
- Results are returned in the same order as the input.
- An invalid order does not abort the batch: it is returned as a failed `BulkOrderResultData` and the remaining orders are still sent.
- Failed entries returned by SteadFast are mapped to `successful: false` with an `error` message.

### Check delivery status

```php
use Thbappy7706\SteadFastCourier\Facades\SteadFastCourier;

$byConsignment = SteadFastCourier::checkDeliveryStatusByConsignmentId(1424107);
$byInvoice     = SteadFastCourier::checkDeliveryStatusByInvoiceId('INV-1001');
$byTracking    = SteadFastCourier::checkDeliveryStatusByTrackingCode('15BAEB8A');

$byConsignment->status;      // DeliveryStatus enum
$byConsignment->status->label();
$byConsignment->toArray();   // ['delivery_status' => 'in_review']
```

### Check the current balance

```php
$balance = SteadFastCourier::getCurrentBalance();

$balance->currentBalance; // 1250.75
```

### Delivery statuses

| Status | Meaning |
| --- | --- |
| `pending` | Not yet delivered or cancelled |
| `delivered_approval_pending` | Delivered, awaiting admin approval |
| `partial_delivered_approval_pending` | Partially delivered, awaiting approval |
| `cancelled_approval_pending` | Cancelled, awaiting approval |
| `unknown_approval_pending` | Unknown state, awaiting support intervention |
| `delivered` | Delivered and balance updated |
| `partial_delivered` | Partially delivered and balance updated |
| `cancelled` | Cancelled and balance updated |
| `hold` | On hold |
| `in_review` | Order placed, under review |
| `unknown` | Unknown status; contact support |

The `DeliveryStatus` enum provides `label()`, `isFinal()`, `isPendingApproval()`, `isSuccessful()` and `tryFromOrUnknown()`. Unknown statuses returned by the API never throw; they map to `DeliveryStatus::Unknown`.

## Webhooks

Set the callback URL and auth token in the SteadFast merchant panel. The built-in route is opt-in:

```env
STEADFAST_WEBHOOK_ENABLED=true
STEADFAST_WEBHOOK_PATH="steadfast/webhook"
STEADFAST_BEARER_TOKEN="your-webhook-token"
```

The route is registered as `POST /steadfast/webhook` (name: `steadfast-courier.webhook`) with the configured middleware plus `VerifyWebhookToken`. It validates `consignment_id`, `invoice`, `status`, `cod_amount` and `updated_at`, tolerates extra fields, and dispatches the `DeliveryStatusUpdated` event:

```php
use Illuminate\Support\Facades\Event;
use Thbappy7706\SteadFastCourier\Events\DeliveryStatusUpdated;

Event::listen(function (DeliveryStatusUpdated $event): void {
    $payload = $event->payload;

    $payload->consignmentId;
    $payload->invoice;
    $payload->status;      // DeliveryStatus enum
    $payload->codAmount;   // float
    $payload->updatedAt;   // CarbonImmutable
});
```

Requests with an incorrect or missing bearer token receive `401 {"error":"Unauthorized"}`. If `STEADFAST_BEARER_TOKEN` is not configured, the route responds with a server error rather than silently accepting callbacks.

### Use your own webhook route

Disable the built-in route and reuse the middleware and event with your own controller:

```php
use Illuminate\Support\Facades\Route;
use Thbappy7706\SteadFastCourier\Http\Middleware\VerifyWebhookToken;

Route::post('/my-webhook', MyWebhookController::class)
    ->middleware(VerifyWebhookToken::class);
```

### Multi-tenant configuration

`withConfig()` returns a **new** client and never mutates the facade singleton, so one application can serve several SteadFast accounts safely:

```php
$tenantClient = SteadFastCourier::withConfig(
    apiKey: $shop->steadfast_api_key,
    secretKey: $shop->steadfast_secret_key,
    baseUrl: 'https://portal.steadfast.com.bd/api/v1',
);

$tenantClient->getCurrentBalance();

// The application-wide client still uses the .env credentials.
SteadFastCourier::getCurrentBalance();
```

> Credentials are marked with `#[\SensitiveParameter]`, and logging never includes headers or bodies.

## Error handling

All package exceptions extend `Thbappy7706\SteadFastCourier\Exceptions\SteadFastException`.

| Exception | When it is thrown |
| --- | --- |
| `ConfigurationException` | API or webhook credentials are missing |
| `AuthenticationException` | The API responds with 401 or 403 |
| `ValidationException` | The API responds with 400 or 422; `$exception->errors` contains the field errors |
| `RequestFailedException` | Other non-2xx responses, connection failures or malformed bodies; carries `statusCode` and `responseBody` |
| `InvalidWebhookException` | A webhook payload cannot be converted into a DTO |

```php
use Thbappy7706\SteadFastCourier\Exceptions\SteadFastException;

try {
    SteadFastCourier::placeOrder($order);
} catch (SteadFastException $exception) {
    report($exception);
}
```

Retry behaviour:

- GET requests are retried on connection errors, `429` and `5xx` responses (`STEADFAST_RETRY_TIMES`, default 2 attempts).
- Validation and authentication failures are never retried.
- `POST /create_order` is never retried, because a duplicate request could create a duplicate consignment.

## Testing

```bash
composer test          # Pest
composer test-coverage # Pest with coverage (requires Xdebug or PCOV)
composer analyse       # Larastan, level 8
composer format        # Pint
composer format:check  # Pint in check mode
composer ci            # format check + Larastan + Pest
```

All HTTP calls are faked in the test suite; no real network requests are made.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for recent changes.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## Security

See [SECURITY.md](SECURITY.md). Never commit API keys or webhook tokens.

## Credits

- [Tanvir Hossen Bappy](https://github.com/thbappy7706)
- Inspired by the official [`steadfast-courier/steadfast-courier-laravel-package`](https://github.com/steadfast-it/SteadFast-Courier-Laravel-Package) and the SteadFast Courier API documentation.

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
