# Changelog

All notable changes to `laravel-steadfast-courier` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-10-28

### Added

- Laravel 13 package for the SteadFast Courier merchant API.
- Typed client contract `SteadFastClient` with `placeOrder()`, `bulkCreateOrders()`,
  `checkDeliveryStatusByConsignmentId()`, `checkDeliveryStatusByInvoiceId()`,
  `checkDeliveryStatusByTrackingCode()`, `getCurrentBalance()` and `withConfig()`.
- Readonly DTOs: `OrderData`, `ConsignmentData`, `BulkOrderResultData`,
  `DeliveryStatusData`, `BalanceData` and `WebhookPayloadData`, each with
  `fromArray()` and `toArray()` (orders also expose `toPayload()`).
- `DeliveryStatus` and `DeliveryType` enums, including `label()`, `isFinal()`,
  `isPendingApproval()`, `isSuccessful()` and `tryFromOrUnknown()`.
- Early client-side validation of order data with a `ValidationException`
  carrying field errors.
- Automatic bulk chunking (500 orders per request) with per-order results that
  preserve input order and never abort the batch for a single invalid order.
- Immutable `withConfig()` that returns a new client instead of mutating the
  shared singleton.
- REST error mapping: 401/403 to `AuthenticationException`, 400/422 to
  `ValidationException`, other failures to `RequestFailedException`.
- Retries for idempotent GET requests on connection errors, 429 and 5xx, with
  order creation explicitly excluded.
- Opt-in webhook route, constant-time bearer token verification, form request
  validation, `DeliveryStatusUpdated` event and `WebhookPayloadData` DTO.
- Optional redacted request logging (method, URL and status only; never headers
  or bodies).
- Pest 4 unit, feature and architecture tests; Larastan level 8 and Laravel Pint.
- GitHub Actions workflows for tests, static analysis and Pint, plus Dependabot.

### Decisions

- **Pest 4 instead of Pest 5.** Pest 5 requires PHP 8.4, while this package
  supports PHP 8.3 to match the Laravel 13 minimum.
- **Laravel 13 only.** The package declares `^13.0` for its Illuminate
  dependencies; older Laravel versions are not tested and are not advertised.
- **No direct Guzzle dependency.** All HTTP traffic goes through Laravel's
  `Illuminate\Http\Client`.
- **Base URL default.** The default is `https://portal.steadfast.com.bd/api/v1`
  as specified in the build guide. The older official package used
  `https://portal.packzy.com/api/v1`; both remain configurable through
  `STEADFAST_BASE_URL`.
- **Bulk endpoint.** Bulk creation uses `POST /create_order/bulk-create` with a
  `{"data": [...]}` body and a 500-order chunk limit. Community integrations
  also reference `/create_order/bulk-order` variants, so confirm the endpoint
  against your merchant API documentation if your account differs.
- **Address limit.** Order validation enforces the 250-character address limit
  from the build guide. SteadFast's 2026 merchant guide reportedly allows up to
  490 characters; this could not be verified against a live account.
- **Webhook payload.** Validation requires `consignment_id`, `invoice`,
  `status`, `cod_amount` and `updated_at`, and tolerates additional fields
  such as `notification_type`, `delivery_charge` and `tracking_message`.
- **Optional endpoints not implemented.** Return requests, payments and police
  station lookups were not included because their availability could not be
  confirmed from the official documentation.
