<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Client\BaseLinkerClient;
use App\Enum\MarketPlaceEnum;
use App\Message\FetchMarketPlaceOrdersMessage;
use App\Performance\PerformanceLogger;
use App\Request\BaseLinkerRequestFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class FetchMarketPlaceOrdersMessageHandler
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly BaseLinkerRequestFactory $requestFactory,
        private readonly BaseLinkerClient $client,
        private readonly PerformanceLogger $performanceLogger,
    ) {
    }

    public function __invoke(FetchMarketPlaceOrdersMessage $message): void
    {
        $marketplace = $message->getMarketPlace();

        $this->logger->info('Starting order synchronization', [
            'marketplace' => $marketplace->value,
        ]);

        $this->performanceLogger->startMeasure('fetch_orders');
        $orders = $this->fetchOrders($marketplace);
        $this->performanceLogger->endMeasure('fetch_orders');

        if (empty($orders)) {
            $this->logger->info('No orders to synchronize', [
                'marketplace' => $marketplace->value,
            ]);
            return;
        }

        $this->performanceLogger->startMeasure('fetch_statuses');
        $statuses = $this->fetchOrderStatuses();
        $this->performanceLogger->endMeasure('fetch_statuses');

        $this->processOrders($orders, $statuses, $marketplace);

        $this->logger->info('Order synchronization completed successfully', [
            'marketplace' => $marketplace->value,
            'orders_processed' => count($orders),
        ]);
    }



    private function fetchOrders(MarketPlaceEnum $marketplace): array
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


    private function fetchOrderStatuses(): array
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

    private function processOrders(array $orders, array $statuses, MarketPlaceEnum $marketplace): void
    {
        $this->logger->info('Processing orders', [
            'marketplace' => $marketplace->value,
            'orders_count' => count($orders),
            'statuses_count' => count($statuses),
        ]);
    }
}
