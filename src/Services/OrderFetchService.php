<?php

declare(strict_types=1);

namespace App\Services;

use App\Client\BaseLinkerClient;
use App\Enum\MarketPlaceEnum;
use App\Request\BaseLinkerRequestFactory;
use Psr\Log\LoggerInterface;

readonly class OrderFetchService
{
    public function __construct(
        private BaseLinkerClient $client,
        private BaseLinkerRequestFactory $requestFactory,
        private LoggerInterface $logger
    ) {
    }

    public function fetchOrders(MarketPlaceEnum $marketplace): array
    {
        $this->logger->debug('Fetching orders from BaseLinker', [
            'marketplace' => $marketplace->value,
        ]);

        $request = $this->requestFactory->createGetOrdersRequest($marketplace);
        $response = $this->client->request($request);

        $orders = $response['orders'] ?? [];

        $this->logger->debug('Orders fetched', [
            'marketplace' => $marketplace->value,
            'count' => count($orders),
        ]);

        return $orders;
    }

    public function fetchOrderStatuses(): array
    {
        $this->logger->debug('Fetching order statuses');

        $request = $this->requestFactory->createGetOrderStatusListRequest();
        $response = $this->client->request($request);

        $statuses = $response['statuses'] ?? [];

        $this->logger->debug('Order statuses fetched', [
            'count' => count($statuses),
        ]);

        return $statuses;
    }
}
