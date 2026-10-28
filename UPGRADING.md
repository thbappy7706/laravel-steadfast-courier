# Upgrading from `steadfast-courier/steadfast-courier-laravel-package`

This package keeps the same config file name, `.env` keys and facade method
names, but changes the namespace and returns typed objects instead of arrays.

## 1. Replace the package

```bash
composer remove steadfast-courier/steadfast-courier-laravel-package
composer require thbappy7706/laravel-steadfast-courier
```

Remove the old service provider and facade from `config/app.php` if you
registered them manually. Auto-discovery registers the new provider and the
`SteadFastCourier` alias.

Republish the configuration file:

```bash
php artisan vendor:publish --tag="steadfast-courier-config" --force
```

Your existing credentials keep working:

```env
STEADFAST_API_KEY="your-api-key"
STEADFAST_SECRET_KEY="your-secret-key"
STEADFAST_BEARER_TOKEN="your-webhook-token"
```

`STEADFAST_BASE_URL` is optional; the package defaults to
`https://portal.steadfast.com.bd/api/v1`.

## 2. Update namespaces

| Old | New |
| --- | --- |
| `SteadFast\SteadFastCourierLaravelPackage\SteadfastCourier` | `Thbappy7706\SteadFastCourier\SteadFastCourier` |
| `SteadFast\SteadFastCourierLaravelPackage\Facades\SteadfastCourier` | `Thbappy7706\SteadFastCourier\Facades\SteadFastCourier` |
| `SteadFast\SteadFastCourierLaravelPackage\LaravelSteadfastCourierServiceProvider` | `Thbappy7706\SteadFastCourier\SteadFastCourierServiceProvider` |

The facade alias (`SteadFastCourier`) is unchanged.

## 3. Responses are objects, not arrays

Every method now returns a typed DTO. Call `toArray()` when you still need the
old array shape.

### Place order

```php
// Before
$response = SteadfastCourier::placeOrder($orderData);
$trackingCode = $response['consignment']['tracking_code'];

// After
$consignment = SteadFastCourier::placeOrder($orderData);
$trackingCode = $consignment->trackingCode;

// Old shape when needed
$array = $consignment->toArray();
```

### Bulk order

```php
// Before
$response = SteadfastCourier::bulkCreateOrders($orders);
$response[0]['status']; // "success"

// After
$results = SteadFastCourier::bulkCreateOrders($orders);
$results[0]->successful; // true
$results[0]->error;      // null, or the reason the order failed
```

The bulk call no longer throws for an individual invalid order. That order is
returned as a failed result while the rest of the batch is still sent.

### Delivery status

```php
// Before
$response = SteadfastCourier::checkDeliveryStatusByInvoiceId('INV-1');
$status = $response['delivery_status'];

// After
$status = SteadfastCourier::checkDeliveryStatusByInvoiceId('INV-1')->status; // DeliveryStatus enum
$status->value; // "in_review"
$status->label(); // "In review"
```

### Balance

```php
// Before
$response = SteadfastCourier::getCurrentBalance();
$balance = $response['current_balance'];

// After
$balance = SteadFastCourier::getCurrentBalance()->currentBalance; // float
```

## 4. `withConfig()` is now immutable

The old implementation mutated the shared singleton. The new one returns a new
client, leaving the application-wide instance untouched:

```php
$tenant = SteadFastCourier::withConfig($apiKey, $secretKey);

$tenant->getCurrentBalance();
SteadFastCourier::getCurrentBalance(); // still uses the .env credentials
```

If your old code relied on the mutation, store and reuse the returned client
explicitly.

## 5. Exceptions instead of silent arrays

Failures now throw exceptions that extend
`Thbappy7706\SteadFastCourier\Exceptions\SteadFastException`:

| Exception | Thrown for |
| --- | --- |
| `ConfigurationException` | Missing API keys or webhook token |
| `AuthenticationException` | 401 / 403 |
| `ValidationException` | 400 / 422 (with `->errors`) |
| `RequestFailedException` | Other non-2xx responses, connection failures |
| `InvalidWebhookException` | Unparsable webhook payloads |

Order data is validated before a request is made, so an invalid phone number,
address or COD amount fails immediately.

## 6. Webhook changes

The old README showed manually comparing `Authorization` with `!==`. The new
package ships an opt-in route with constant-time verification:

```env
STEADFAST_WEBHOOK_ENABLED=true
STEADFAST_WEBHOOK_PATH="steadfast/webhook"
```

The route validates the payload and dispatches
`Thbappy7706\SteadFastCourier\Events\DeliveryStatusUpdated`. See the README for
listening to the event or reusing `VerifyWebhookToken` on your own route.

## 7. New configuration keys

| Key | Default | Purpose |
| --- | --- | --- |
| `timeout` | `15` | Per-request timeout in seconds |
| `connect_timeout` | `5` | Connection timeout in seconds |
| `retry.times` | `2` | Attempts for idempotent GET requests |
| `retry.sleep_ms` | `200` | Delay between retries |
| `bulk_chunk_size` | `500` | Orders per bulk request |
| `webhook.enabled` | `false` | Register the built-in webhook route |
| `webhook.path` | `steadfast/webhook` | Webhook route path |
| `webhook.middleware` | `['api']` | Extra route middleware |
| `logging.enabled` | `false` | Log API calls (redacted) |
| `logging.channel` | `null` | Log channel to use |
