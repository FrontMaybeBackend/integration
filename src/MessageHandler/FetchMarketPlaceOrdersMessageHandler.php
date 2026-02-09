<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\MarketPlaceEnum;
use App\Message\FetchMarketPlaceOrdersMessage;
use App\Performance\PerformanceLogger;
use App\Services\OrderFetchService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class FetchMarketPlaceOrdersMessageHandler
{
    public function __construct(
        private LoggerInterface $baselinkerLogger,
        private OrderFetchService $orderFetchService,
        private PerformanceLogger $performanceLogger,
    ) {
    }

    public function __invoke(FetchMarketPlaceOrdersMessage $message): void
    {
        $marketplace = $message->getMarketPlace();

        $this->baselinkerLogger->info('Starting order synchronization', [
            'marketplace' => $marketplace->value,
        ]);

        $result = $this->performanceLogger->measure(
            'fetch_marketplace_data',
            fn() => $this->fetchMarketplaceData($marketplace)
        );

        if (empty($result['orders'])) {
            $this->baselinkerLogger->info('No orders to synchronize', [
                'marketplace' => $marketplace->value,
            ]);
            return;
        }

        $this->processOrders($result['orders'], $result['statuses'], $marketplace);

        $this->baselinkerLogger->info('Order synchronization completed', [
            'marketplace' => $marketplace->value,
            'orders_processed' => count($result['orders']),
        ]);
    }

    private function fetchMarketplaceData(MarketPlaceEnum $marketplace): array
    {
        return [
            'orders' => $this->orderFetchService->fetchOrders($marketplace),
            'statuses' => $this->orderFetchService->fetchOrderStatuses(),
        ];
    }

    private function processOrders(array $orders, array $statuses, MarketPlaceEnum $marketplace): void
    {
        $this->baselinkerLogger->info('Processing orders', [
            'marketplace' => $marketplace->value,
            'orders_count' => count($orders),
            'statuses_count' => count($statuses),
        ]);
    }
}
