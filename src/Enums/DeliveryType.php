<?php

declare(strict_types=1);

namespace Thbappy7706\SteadFastCourier\Enums;

enum DeliveryType: int
{
    case HomeDelivery = 0;
    case PointDelivery = 1;

    public function label(): string
    {
        return match ($this) {
            self::HomeDelivery => 'Home delivery',
            self::PointDelivery => 'Point delivery',
        };
    }
}
