<?php

declare(strict_types=1);

namespace Thbappy7706\SteadFastCourier;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SensitiveParameter;
use Thbappy7706\SteadFastCourier\Contracts\SteadFastClient;
use Thbappy7706\SteadFastCourier\Data\BalanceData;
use Thbappy7706\SteadFastCourier\Data\BulkOrderResultData;
use Thbappy7706\SteadFastCourier\Data\ConsignmentData;
use Thbappy7706\SteadFastCourier\Data\DeliveryStatusData;
use Thbappy7706\SteadFastCourier\Data\OrderData;
use Thbappy7706\SteadFastCourier\Exceptions\AuthenticationException;
use Thbappy7706\SteadFastCourier\Exceptions\RequestFailedException;
use Thbappy7706\SteadFastCourier\Exceptions\SteadFastException;
use Thbappy7706\SteadFastCourier\Exceptions\ValidationException;
use Thbappy7706\SteadFastCourier\Support\SteadFastConfig;
use Throwable;

final readonly class SteadFastCourier implements SteadFastClient
{
    private const BULK_CREATE_ENDPOINT = '/create_order/bulk-create';

    public function __construct(
        private SteadFastConfig $config,
    ) {}

    public function placeOrder(OrderData|array $order): ConsignmentData
    {
        $order = $order instanceof OrderData ? $order : OrderData::fromArray($order);

        $response = $this->request('POST', '/create_order', $order->toPayload());

        return ConsignmentData::fromArray($response);
    }

    public function bulkCreateOrders(iterable $orders): array
    {
        /** @var list<array{order: OrderData|null, result: BulkOrderResultData|null}> $entries */
        $entries = [];

        foreach ($orders as $order) {
            if (! $order instanceof OrderData) {
                if (! is_array($order)) {
                    $entries[] = [
                        'order' => null,
                        'result' => new BulkOrderResultData(
                            invoice: '',
                            successful: false,
                            error: 'Every order must be an OrderData instance or an array.',
                        ),
                    ];

                    continue;
                }

                try {
                    $order = OrderData::fromArray($order);
                } catch (ValidationException $exception) {
                    $invoice = $order['invoice'] ?? null;

                    $entries[] = [
                        'order' => null,
                        'result' => new BulkOrderResultData(
                            invoice: is_string($invoice) ? $invoice : '',
                            successful: false,
                            error: $exception->getMessage(),
                        ),
                    ];

                    continue;
                }
            }

            $entries[] = ['order' => $order, 'result' => null];
        }

        if ($entries === []) {
            return [];
        }

        /** @var list<array{index: int, order: OrderData}> $pending */
        $pending = [];

        foreach ($entries as $index => $entry) {
            $order = $entry['order'] ?? null;

            if ($order instanceof OrderData) {
                $pending[] = ['index' => $index, 'order' => $order];
            }
        }

        /** @var int<1, max> $chunkSize */
        $chunkSize = max(1, $this->config->bulkChunkSize);

        foreach (array_chunk($pending, $chunkSize) as $chunk) {
            $payload = array_map(
                static fn (array $entry): array => $entry['order']->toPayload(),
                $chunk,
            );

            $response = $this->request('POST', self::BULK_CREATE_ENDPOINT, ['data' => $payload]);
            $results = $this->bulkResults($response);
            $resultsByInvoice = [];

            foreach ($results as $result) {
                $resultsByInvoice[$result->invoice] = $result;
            }

            foreach ($chunk as $position => $entry) {
                $result = $resultsByInvoice[$entry['order']->invoice] ?? $results[$position] ?? null;

                $entries[$entry['index']]['result'] = $result ?? new BulkOrderResultData(
                    invoice: $entry['order']->invoice,
                    successful: false,
                    error: 'The SteadFast API did not return a result for this order.',
                );
            }
        }

        $ordered = [];

        foreach ($entries as $entry) {
            $order = $entry['order'] ?? null;
            $invoice = $order instanceof OrderData ? $order->invoice : '';

            $ordered[] = $entry['result'] ?? new BulkOrderResultData(
                invoice: $invoice,
                successful: false,
                error: 'The order could not be processed.',
            );
        }

        return $ordered;
    }

    public function checkDeliveryStatusByConsignmentId(int|string $id): DeliveryStatusData
    {
        return DeliveryStatusData::fromArray(
            $this->request('GET', '/status_by_cid/'.rawurlencode((string) $id), [], retryable: true),
        );
    }

    public function checkDeliveryStatusByInvoiceId(string $invoice): DeliveryStatusData
    {
        return DeliveryStatusData::fromArray(
            $this->request('GET', '/status_by_invoice/'.rawurlencode($invoice), [], retryable: true),
        );
    }

    public function checkDeliveryStatusByTrackingCode(string $code): DeliveryStatusData
    {
        return DeliveryStatusData::fromArray(
            $this->request('GET', '/status_by_trackingcode/'.rawurlencode($code), [], retryable: true),
        );
    }

    public function getCurrentBalance(): BalanceData
    {
        return BalanceData::fromArray(
            $this->request('GET', '/get_balance', [], retryable: true),
        );
    }

    public function withConfig(
        #[SensitiveParameter] string $apiKey,
        #[SensitiveParameter] string $secretKey,
        ?string $baseUrl = null,
    ): self {
        return new self($this->config->withCredentials($apiKey, $secretKey, $baseUrl));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws SteadFastException
     */
    private function request(string $method, string $path, array $payload = [], bool $retryable = false): array
    {
        $this->config->assertHasCredentials();

        $pending = Http::baseUrl($this->config->baseUrl)
            ->withHeaders([
                'Api-Key' => $this->config->apiKey,
                'Secret-Key' => $this->config->secretKey,
            ])
            ->acceptJson()
            ->asJson()
            ->timeout($this->config->timeout)
            ->connectTimeout($this->config->connectTimeout);

        if ($retryable && $this->config->retryTimes > 0) {
            $pending = $pending->retry(
                $this->config->retryTimes,
                $this->config->retrySleepMs,
                static function (Throwable $exception): bool {
                    if ($exception instanceof ConnectionException) {
                        return true;
                    }

                    return $exception instanceof RequestException
                        && ($exception->response->status() === 429 || $exception->response->serverError());
                },
            );
        }

        try {
            $response = $method === 'GET'
                ? $pending->get($path)
                : $pending->post($path, $payload);
        } catch (ConnectionException $exception) {
            throw new RequestFailedException(
                'Could not connect to the SteadFast API: '.$exception->getMessage(),
                previous: $exception,
            );
        } catch (RequestException $exception) {
            throw $this->exceptionFromResponse($exception->response);
        }

        if (! $response->successful()) {
            throw $this->exceptionFromResponse($response);
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new RequestFailedException(
                'The SteadFast API returned an unexpected response body.',
                $response->status(),
                $response->body(),
            );
        }

        $this->logRequest($method, $path, $response->status());

        return $data;
    }

    private function exceptionFromResponse(Response $response): SteadFastException
    {
        $status = $response->status();
        $data = $response->json();
        $body = is_array($data) ? $data : [];
        $message = is_string($body['message'] ?? null) && $body['message'] !== ''
            ? $body['message']
            : 'The SteadFast API request failed with status '.$status.'.';

        return match (true) {
            in_array($status, [401, 403], true) => new AuthenticationException($message, $status),
            in_array($status, [400, 422], true) => ValidationException::fromResponse($body, $status, $message),
            default => new RequestFailedException($message, $status, $response->body()),
        };
    }

    /**
     * @param  array<string, mixed>  $response
     * @return list<BulkOrderResultData>
     */
    private function bulkResults(array $response): array
    {
        $items = array_is_list($response)
            ? $response
            : ($response['data'] ?? null);

        if (! is_array($items)) {
            throw new RequestFailedException('The SteadFast API returned an unexpected bulk order response.');
        }

        $results = [];

        foreach ($items as $item) {
            if (is_array($item)) {
                $results[] = BulkOrderResultData::fromArray($item);
            }
        }

        return $results;
    }

    private function logRequest(string $method, string $path, ?int $status): void
    {
        if (! $this->config->loggingEnabled) {
            return;
        }

        $context = [
            'method' => $method,
            'url' => $this->config->baseUrl.$path,
            'status' => $status,
        ];

        if ($this->config->logChannel !== null) {
            Log::channel($this->config->logChannel)->debug('SteadFast API request', $context);

            return;
        }

        Log::debug('SteadFast API request', $context);
    }
}
