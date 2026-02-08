<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Enum\MarketPlaceEnum;
use App\Message\FetchMarketPlaceOrdersMessage;
use App\MessageHandler\FetchMarketPlaceOrdersMessageHandler;
use App\Performance\PerformanceLogger;
use App\Services\OrderFetchService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
class FetchMarketPlaceOrdersMessageHandlerTest extends TestCase
{
    private LoggerInterface $logger;
    private OrderFetchService $orderFetchService;
    private PerformanceLogger $performanceLogger;
    private FetchMarketPlaceOrdersMessageHandler $handler;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->orderFetchService = $this->createMock(OrderFetchService::class);
        $this->performanceLogger = $this->createMock(PerformanceLogger::class);

        $this->handler = new FetchMarketPlaceOrdersMessageHandler(
            $this->logger,
            $this->orderFetchService,
            $this->performanceLogger
        );
    }

    #[Test]
    public function handleWithNoOrders(): void
    {
        $message = new FetchMarketPlaceOrdersMessage(MarketPlaceEnum::ALLEGRO);

        $this->orderFetchService
            ->expects($this->once())
            ->method('fetchOrders')
            ->with(MarketPlaceEnum::ALLEGRO)
            ->willReturn([]);

        $this->orderFetchService
            ->expects($this->never())
            ->method('fetchOrderStatuses');

        $this->performanceLogger
            ->expects($this->once())
            ->method('measure')
            ->with(
                'fetch_marketplace_data',
                $this->callback(fn($arg) => is_callable($arg))
            )
            ->willReturnCallback(function ($operation, $callback) {
                return $callback();
            });

        $this->logger
            ->expects($this->exactly(2))
            ->method('info');

        ($this->handler)($message);
    }

    #[Test]
    public function handleWithOrders(): void
    {
        $message = new FetchMarketPlaceOrdersMessage(MarketPlaceEnum::AMAZON);

        $orders = [
            ['order_id' => 123, 'status_id' => 1],
            ['order_id' => 456, 'status_id' => 2],
        ];

        $statuses = [
            ['id' => 1, 'name' => 'New'],
            ['id' => 2, 'name' => 'Confirmed'],
        ];

        $this->orderFetchService
            ->expects($this->once())
            ->method('fetchOrders')
            ->with(MarketPlaceEnum::AMAZON)
            ->willReturn($orders);

        $this->orderFetchService
            ->expects($this->once())
            ->method('fetchOrderStatuses')
            ->willReturn($statuses);

        $this->performanceLogger
            ->expects($this->once())
            ->method('measure')
            ->with(
                'fetch_marketplace_data',
                $this->callback(fn($arg) => is_callable($arg))
            )
            ->willReturnCallback(function ($operation, $callback) {
                return $callback();
            });

        $this->logger
            ->expects($this->exactly(3))
            ->method('info');

        ($this->handler)($message);
    }

    #[Test]
    public function handleUsesPerformanceLogger(): void
    {
        $message = new FetchMarketPlaceOrdersMessage(MarketPlaceEnum::ALLEGRO);

        $orders = [['order_id' => 789]];
        $statuses = [['id' => 1, 'name' => 'New']];

        $this->orderFetchService
            ->method('fetchOrders')
            ->willReturn($orders);

        $this->orderFetchService
            ->method('fetchOrderStatuses')
            ->willReturn($statuses);

        $this->performanceLogger
            ->expects($this->once())
            ->method('measure')
            ->with(
                'fetch_marketplace_data',
                $this->callback(function ($callback) {
                    return is_callable($callback);
                })
            )
            ->willReturnCallback(function ($operation, $callback) {
                return $callback();
            });

        ($this->handler)($message);
    }

    #[Test]
    public function handleCallsProcessOrdersWithCorrectData(): void
    {
        $message = new FetchMarketPlaceOrdersMessage(MarketPlaceEnum::PERSONAL);

        $orders = [
            ['order_id' => 1, 'product' => 'Product A'],
            ['order_id' => 2, 'product' => 'Product B'],
        ];

        $statuses = [
            ['id' => 1, 'name' => 'Pending'],
            ['id' => 2, 'name' => 'Shipped'],
        ];

        $this->orderFetchService
            ->method('fetchOrders')
            ->with(MarketPlaceEnum::PERSONAL)
            ->willReturn($orders);

        $this->orderFetchService
            ->method('fetchOrderStatuses')
            ->willReturn($statuses);

        $this->performanceLogger
            ->method('measure')
            ->willReturnCallback(fn($op, $cb) => $cb());

        $infoCallCount = 0;
        $this->logger
            ->expects($this->atLeastOnce())
            ->method('info')
            ->willReturnCallback(function ($message, $context) use (&$infoCallCount) {
                $infoCallCount++;
                $this->assertContains($message, [
                    'Starting order synchronization',
                    'Processing orders',
                    'Order synchronization completed',
                ]);
            });

        ($this->handler)($message);

        $this->assertSame(3, $infoCallCount);
    }

    #[Test]
    public function handleLogsStartMessage(): void
    {
        $message = new FetchMarketPlaceOrdersMessage(MarketPlaceEnum::ALLEGRO);

        $this->orderFetchService
            ->method('fetchOrders')
            ->willReturn([]);

        $this->performanceLogger
            ->method('measure')
            ->willReturnCallback(fn($op, $cb) => $cb());

        $loggedStartMessage = false;

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('info')
            ->willReturnCallback(function ($message, $context) use (&$loggedStartMessage) {
                if ($message === 'Starting order synchronization') {
                    $loggedStartMessage = true;
                    $this->assertSame('ALLEGRO', $context['marketplace']);
                }
            });

        ($this->handler)($message);

        $this->assertTrue($loggedStartMessage, 'Start message was not logged');
    }

    #[Test]
    public function handleLogsNoOrdersMessage(): void
    {
        $message = new FetchMarketPlaceOrdersMessage(MarketPlaceEnum::AMAZON);

        $this->orderFetchService
            ->method('fetchOrders')
            ->willReturn([]);

        $this->performanceLogger
            ->method('measure')
            ->willReturnCallback(fn($op, $cb) => $cb());

        $loggedNoOrdersMessage = false;

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('info')
            ->willReturnCallback(function ($message, $context) use (&$loggedNoOrdersMessage) {
                if ($message === 'No orders to synchronize') {
                    $loggedNoOrdersMessage = true;
                    $this->assertSame('AMAZON', $context['marketplace']);
                }
            });

        ($this->handler)($message);

        $this->assertTrue($loggedNoOrdersMessage, 'No orders message was not logged');
    }

    #[Test]
    public function handleLogsCompletionMessage(): void
    {
        $message = new FetchMarketPlaceOrdersMessage(MarketPlaceEnum::PERSONAL);

        $orders = [['order_id' => 1], ['order_id' => 2]];

        $this->orderFetchService
            ->method('fetchOrders')
            ->willReturn($orders);

        $this->orderFetchService
            ->method('fetchOrderStatuses')
            ->willReturn([]);

        $this->performanceLogger
            ->method('measure')
            ->willReturnCallback(fn($op, $cb) => $cb());

        $loggedCompletionMessage = false;

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('info')
            ->willReturnCallback(function ($message, $context) use (&$loggedCompletionMessage) {
                if ($message === 'Order synchronization completed') {
                    $loggedCompletionMessage = true;
                    $this->assertSame('PERSONAL', $context['marketplace']);
                    $this->assertSame(2, $context['orders_processed']);
                }
            });

        ($this->handler)($message);

        $this->assertTrue($loggedCompletionMessage, 'Completion message was not logged');
    }
}
