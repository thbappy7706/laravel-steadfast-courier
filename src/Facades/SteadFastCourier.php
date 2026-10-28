<?php

declare(strict_types=1);

namespace Thbappy7706\SteadFastCourier\Facades;

use Illuminate\Support\Facades\Facade;
use Thbappy7706\SteadFastCourier\Contracts\SteadFastClient;
use Thbappy7706\SteadFastCourier\Data\BalanceData;
use Thbappy7706\SteadFastCourier\Data\BulkOrderResultData;
use Thbappy7706\SteadFastCourier\Data\ConsignmentData;
use Thbappy7706\SteadFastCourier\Data\DeliveryStatusData;
use Thbappy7706\SteadFastCourier\Data\OrderData;
use Thbappy7706\SteadFastCourier\SteadFastCourier as Client;

/**
 * @method static ConsignmentData placeOrder(OrderData|array<string, mixed> $order)
 * @method static list<BulkOrderResultData> bulkCreateOrders(iterable<array-key, OrderData|array<string, mixed>> $orders)
 * @method static DeliveryStatusData checkDeliveryStatusByConsignmentId(int|string $id)
 * @method static DeliveryStatusData checkDeliveryStatusByInvoiceId(string $invoice)
 * @method static DeliveryStatusData checkDeliveryStatusByTrackingCode(string $code)
 * @method static BalanceData getCurrentBalance()
 * @method static Client withConfig(string $apiKey, string $secretKey, ?string $baseUrl = null)
 *
 * @see Client
 */
final class SteadFastCourier extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SteadFastClient::class;
    }
}
