<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Thbappy7706\SteadFastCourier\Contracts\SteadFastClient;
use Thbappy7706\SteadFastCourier\Enums\DeliveryStatus;
use Thbappy7706\SteadFastCourier\Facades\SteadFastCourier as SteadFastCourierFacade;
use Thbappy7706\SteadFastCourier\Http\Middleware\VerifyWebhookToken;
use Thbappy7706\SteadFastCourier\SteadFastCourier;
use Thbappy7706\SteadFastCourier\SteadFastCourierServiceProvider;
use Thbappy7706\SteadFastCourier\Support\SteadFastConfig;
use Thbappy7706\SteadFastCourier\Tests\TestCase;

uses(TestCase::class);

it('registers the configuration as a singleton', function (): void {
    $first = app(SteadFastConfig::class);
    $second = app(SteadFastConfig::class);

    expect($first)->toBe($second)
        ->and($first->apiKey)->toBe('test-api-key')
        ->and($first->secretKey)->toBe('test-secret-key')
        ->and($first->baseUrl)->toBe('https://portal.steadfast.com.bd/api/v1');
});

it('registers the client contract and concrete class as one singleton', function (): void {
    $contract = app(SteadFastClient::class);
    $concrete = app(SteadFastCourier::class);

    expect($contract)->toBeInstanceOf(SteadFastCourier::class)
        ->and($concrete)->toBe($contract)
        ->and(app(SteadFastClient::class))->toBe($contract);
});

it('resolves the client through the facade', function (): void {
    expect(SteadFastCourierFacade::getFacadeRoot())->toBe(app(SteadFastClient::class));
});

it('forwards API calls through the facade', function (): void {
    Http::fake([
        '*/get_balance' => Http::response(['current_balance' => 42]),
        '*/status_by_cid/1' => Http::response(['delivery_status' => 'delivered']),
    ]);

    $balance = SteadFastCourierFacade::getCurrentBalance();
    $status = SteadFastCourierFacade::checkDeliveryStatusByConsignmentId(1);

    expect($balance->currentBalance)->toBe(42.0)
        ->and($status->status)->toBe(DeliveryStatus::Delivered);
});

it('publishes the configuration file', function (): void {
    $paths = ServiceProvider::pathsToPublish(
        SteadFastCourierServiceProvider::class,
        'steadfast-courier-config',
    );

    $sources = array_map(
        static fn (string $path): string|false => realpath($path),
        array_keys($paths),
    );

    expect($paths)->not->toBeEmpty()
        ->and($sources)->toContain(realpath(__DIR__.'/../../config/steadfast-courier.php'));
});

it('registers the webhook route when it is enabled', function (): void {
    expect(Route::has('steadfast-courier.webhook'))->toBeTrue();

    $route = app('router')->getRoutes()->getByName('steadfast-courier.webhook');

    expect($route)->not->toBeNull()
        ->and($route->uri())->toBe('steadfast/webhook')
        ->and($route->methods())->toContain('POST')
        ->and($route->gatherMiddleware())->toContain(VerifyWebhookToken::class)
        ->and(route('steadfast-courier.webhook'))->toEndWith('/steadfast/webhook');
});

it('returns a new client from withConfig without mutating the facade singleton', function (): void {
    Http::fake([
        'https://tenant.test/api/v1/*' => Http::response(steadfastConsignmentPayload()),
        'https://portal.steadfast.com.bd/api/v1/get_balance' => Http::response(['current_balance' => 10]),
    ]);

    $original = SteadFastCourierFacade::getFacadeRoot();
    $tenant = $original->withConfig('tenant-key', 'tenant-secret', 'https://tenant.test/api/v1');

    expect($tenant)->not->toBe($original)
        ->and($tenant)->toBeInstanceOf(SteadFastCourier::class)
        ->and(SteadFastCourierFacade::getFacadeRoot())->toBe($original);

    $tenant->placeOrder(steadfastOrderPayload());

    Http::assertSent(fn ($request): bool => $request->url() === 'https://tenant.test/api/v1/create_order'
        && $request->hasHeader('Api-Key', 'tenant-key')
        && $request->hasHeader('Secret-Key', 'tenant-secret'));

    $original->getCurrentBalance();

    Http::assertSent(fn ($request): bool => $request->url() === 'https://portal.steadfast.com.bd/api/v1/get_balance'
        && $request->hasHeader('Api-Key', 'test-api-key')
        && $request->hasHeader('Secret-Key', 'test-secret-key'));
});

it('sends requests to a different base url when withConfig receives one', function (): void {
    Http::fake([
        '*' => Http::response(['delivery_status' => 'delivered']),
    ]);

    $client = app(SteadFastClient::class)->withConfig(
        apiKey: 'key',
        secretKey: 'secret',
        baseUrl: 'https://staging.test/api/v2/',
    );

    expect($client->checkDeliveryStatusByConsignmentId(123)->status->value)->toBe('delivered');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://staging.test/api/v2/status_by_cid/123');
});
