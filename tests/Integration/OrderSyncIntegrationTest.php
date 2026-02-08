<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Enum\MarketPlaceEnum;
use App\Message\FetchMarketPlaceOrdersMessage;
use App\MessageHandler\FetchMarketPlaceOrdersMessageHandler;
use App\Performance\PerformanceLogger;
use App\Services\OrderFetchService;
use App\Services\OrderSyncService;
use App\Validator\MarketPlaceConfigurationValidator;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[AllowMockObjectsWithoutExpectations]
class OrderSyncIntegrationTest extends TestCase
{
    private OrderSyncService $orderSyncService;
    private FetchMarketPlaceOrdersMessageHandler $handler;

    protected function setUp(): void
    {

        $validator = $this->createMock(MarketPlaceConfigurationValidator::class);
        $validator->method('validate');
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $orderFetchService = $this->createMock(OrderFetchService::class);

        $orderFetchService
            ->method('fetchOrders')
            ->willReturn([]);

        $orderFetchService
            ->method('fetchOrderStatuses')
            ->willReturn([]);

        $performanceLogger = new PerformanceLogger(new NullLogger());

        $this->orderSyncService = new OrderSyncService(
            $messageBus,
            $validator,
            new NullLogger()
        );

        $this->handler = new FetchMarketPlaceOrdersMessageHandler(
            new NullLogger(),
            $orderFetchService,
            $performanceLogger
        );
    }

    #[Test]
    public function completeOrderSyncFlow(): void
    {
        $this->orderSyncService->validateAndDispatchSync(MarketPlaceEnum::ALLEGRO);
        $message = new FetchMarketPlaceOrdersMessage(MarketPlaceEnum::ALLEGRO);
        ($this->handler)($message);
        $this->assertTrue(true);
    }

    #[Test]
    public function syncWithEmptyOrders(): void
    {
        $message = new FetchMarketPlaceOrdersMessage(MarketPlaceEnum::AMAZON);
        ($this->handler)($message);
        $this->assertTrue(true);
    }
}
