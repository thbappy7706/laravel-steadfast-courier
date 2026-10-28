<?php

declare(strict_types=1);

namespace Thbappy7706\SteadFastCourier\Events;

use Thbappy7706\SteadFastCourier\Data\WebhookPayloadData;

final class DeliveryStatusUpdated
{
    public function __construct(
        public readonly WebhookPayloadData $payload,
    ) {}
}
