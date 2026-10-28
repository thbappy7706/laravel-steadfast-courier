# Contributing

Thank you for considering a contribution to `laravel-steadfast-courier`.

## Development setup

```bash
git clone https://github.com/thbappy7706/laravel-steadfast-courier.git
cd laravel-steadfast-courier
composer install
```

The package requires PHP 8.3 or newer and targets Laravel 13.

## Before opening a pull request

Run the full quality gate:

```bash
composer ci
```

This runs:

1. `composer format:check` — Laravel Pint style check.
2. `composer analyse` — Larastan at level 8.
3. `composer test` — the Pest suite.

Apply formatting fixes with `composer format`.

## Guidelines

- Add or update tests for every behaviour change. HTTP calls must be faked.
- Keep `declare(strict_types=1);` in every PHP file.
- Use typed DTOs and enums instead of arrays in the public API.
- Follow the existing exception hierarchy; do not throw generic exceptions.
- Do not log credentials. `Api-Key`, `Secret-Key` and bearer tokens must never
  appear in logs, exception messages or test snapshots.
- Keep the webhook endpoint opt-in and verify tokens in constant time.
- Update `CHANGELOG.md` for user-facing changes.

## Reporting bugs

Open an issue with the package version, Laravel and PHP versions, the expected
behaviour, the actual behaviour and a minimal reproduction. Never include real
API keys or customer data.
