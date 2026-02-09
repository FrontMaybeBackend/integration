<?php

declare(strict_types=1);

namespace App\Services\Paginator;

use App\Client\BaseLinkerClient;
use App\Enum\MarketPlaceEnum;
use App\Request\BaseLinkerRequestFactory;
use Psr\Log\LoggerInterface;

readonly class BaseLinkerOrderPaginator
{
    private const PAGE_SIZE = 100;

    public function __construct(
        private BaseLinkerClient $client,
        private BaseLinkerRequestFactory $requestFactory,
        private LoggerInterface $baselinkerLogger
    ) {
    }


    public function fetchAll(MarketPlaceEnum $marketplace, ?int $dateFrom = null): array
    {
        $allOrders = [];
        $lastOrderId = null;
        $pageNumber = 1;

        $this->baselinkerLogger->debug('Starting pagination', [
            'marketplace' => $marketplace->value,
            'date_from' => $dateFrom ?? time() - 86400,
        ]);

        do {
            $pageOrders = $this->fetchPage($marketplace, $dateFrom, $lastOrderId, $pageNumber);

            if (empty($pageOrders)) {
                $this->baselinkerLogger->debug('No more orders to fetch', [
                    'marketplace' => $marketplace->value,
                    'total_pages' => $pageNumber - 1,
                ]);
                break;
            }

            $allOrders = array_merge($allOrders, $pageOrders);
            $lastOrderId = $this->extractLastOrderId($pageOrders);

            $this->baselinkerLogger->debug('Page fetched and merged', [
                'marketplace' => $marketplace->value,
                'page' => $pageNumber,
                'page_count' => count($pageOrders),
                'total_count' => count($allOrders),
                'last_order_id' => $lastOrderId,
            ]);

            $pageNumber++;
        } while (count($pageOrders) === self::PAGE_SIZE);

        $this->baselinkerLogger->debug('Pagination completed', [
            'marketplace' => $marketplace->value,
            'total_orders' => count($allOrders),
            'total_pages' => $pageNumber - 1,
        ]);

        return $allOrders;
    }


    private function fetchPage(
        MarketPlaceEnum $marketplace,
        ?int $dateFrom,
        ?int $lastOrderId,
        int $pageNumber
    ): array {
        $this->baselinkerLogger->debug('Fetching single page', [
            'marketplace' => $marketplace->value,
            'page' => $pageNumber,
            'last_order_id' => $lastOrderId,
        ]);

        $request = $this->requestFactory->createGetOrdersRequest(
            $marketplace,
            dateFrom: $dateFrom,
            idFrom: $lastOrderId
        );

        $response = $this->client->request($request);
        $orders = $response['orders'] ?? [];

        $this->baselinkerLogger->debug('Single page fetched from API', [
            'marketplace' => $marketplace->value,
            'page' => $pageNumber,
            'count' => count($orders),
        ]);

        return $orders;
    }


    private function extractLastOrderId(array $orders): ?int
    {
        if (empty($orders)) {
            return null;
        }

        $lastOrder = end($orders);
        return $lastOrder['order_id'] ?? null;
    }
}
