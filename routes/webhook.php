<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Thbappy7706\SteadFastCourier\Http\Controllers\WebhookController;
use Thbappy7706\SteadFastCourier\Http\Middleware\VerifyWebhookToken;

$path = config('steadfast-courier.webhook.path');
$path = is_string($path) && trim($path) !== '' ? $path : 'steadfast/webhook';

$middleware = config('steadfast-courier.webhook.middleware');
$middleware = is_array($middleware)
    ? array_values(array_filter($middleware, static fn (mixed $value): bool => is_string($value)))
    : ['api'];

$middleware[] = VerifyWebhookToken::class;

Route::middleware($middleware)
    ->post('/'.ltrim($path, '/'), WebhookController::class)
    ->name('steadfast-courier.webhook');
