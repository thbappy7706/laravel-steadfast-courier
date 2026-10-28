<?php

declare(strict_types=1);

namespace Thbappy7706\SteadFastCourier\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Thbappy7706\SteadFastCourier\Events\DeliveryStatusUpdated;
use Thbappy7706\SteadFastCourier\Http\Requests\WebhookRequest;

final class WebhookController
{
    public function __invoke(WebhookRequest $request): JsonResponse
    {
        event(new DeliveryStatusUpdated($request->toData()));

        return response()->json(['status' => 'success']);
    }
}
