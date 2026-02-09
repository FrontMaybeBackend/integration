<?php

declare(strict_types=1);

namespace App\Services;

use App\Client\BaseLinkerClient;
use App\Enum\MarketPlaceEnum;
use App\Request\BaseLinkerRequestFactory;
use App\Services\Paginator\BaseLinkerOrderPaginator;
use Psr\Log\LoggerInterface;

readonly class OrderFetchService
{
    public function __construct(
        private BaseLinkerClient $client,
        private BaseLinkerRequestFactory $requestFactory,
        private LoggerInterface $baselinkerLogger,
        private BaseLinkerOrderPaginator $paginator,
    ) {
    }

    public function fetchOrders(MarketPlaceEnum $marketplace, ?int $dateFrom = null): array
    {
        $this->baselinkerLogger->info('Starting to fetch orders', [
            'marketplace' => $marketplace->value,
            'date_from' => $dateFrom ?? time() - 86400,
        ]);

        $orders = $this->paginator->fetchAll($marketplace, $dateFrom);

        $this->baselinkerLogger->info('Finished fetching orders', [
            'marketplace' => $marketplace->value,
            'total_orders' => count($orders),
        ]);

        return $orders;
    }

    public function fetchOrderStatuses(): array
    {
        $this->baselinkerLogger->debug('Fetching order statuses');

        $request = $this->requestFactory->createGetOrderStatusListRequest();
        $response = $this->client->request($request);

        $statuses = $response['statuses'] ?? [];

        $this->baselinkerLogger->debug('Order statuses fetched', [
            'count' => count($statuses),
        ]);

        return $statuses;
    }
}
