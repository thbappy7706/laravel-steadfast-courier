<?php

declare(strict_types=1);

namespace Thbappy7706\SteadFastCourier\Contracts;

use Thbappy7706\SteadFastCourier\Data\BalanceData;
use Thbappy7706\SteadFastCourier\Data\BulkOrderResultData;
use Thbappy7706\SteadFastCourier\Data\ConsignmentData;
use Thbappy7706\SteadFastCourier\Data\DeliveryStatusData;
use Thbappy7706\SteadFastCourier\Data\OrderData;

interface SteadFastClient
{
    /**
     * @param  OrderData|array<string, mixed>  $order
     */
    public function placeOrder(OrderData|array $order): ConsignmentData;

    /**
     * @param  iterable<array-key, OrderData|array<string, mixed>>  $orders
     * @return list<BulkOrderResultData>
     */
    public function bulkCreateOrders(iterable $orders): array;

    public function checkDeliveryStatusByConsignmentId(int|string $id): DeliveryStatusData;

    public function checkDeliveryStatusByInvoiceId(string $invoice): DeliveryStatusData;

    public function checkDeliveryStatusByTrackingCode(string $code): DeliveryStatusData;

    public function getCurrentBalance(): BalanceData;

    /**
     * Return a new client that uses the given credentials. The instance this
     * method is called on is never mutated.
     */
    public function withConfig(string $apiKey, string $secretKey, ?string $baseUrl = null): self;
}
