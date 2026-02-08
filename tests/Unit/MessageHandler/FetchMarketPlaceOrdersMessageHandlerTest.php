<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Client\BaseLinkerClient;
use App\Enum\MarketPlaceEnum;
use App\Message\FetchMarketPlaceOrdersMessage;
use App\MessageHandler\FetchMarketPlaceOrdersMessageHandler;
use App\Performance\PerformanceLogger;
use App\Request\BaseLinkerRequest;
use App\Request\BaseLinkerRequestFactory;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
class FetchMarketPlaceOrdersMessageHandlerTest extends TestCase
{
    private LoggerInterface $logger;
    private BaseLinkerRequestFactory $requestFactory;
    private BaseLinkerClient $client;
    private FetchMarketPlaceOrdersMessageHandler $handler;
    private PerformanceLogger $performanceLogger;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->requestFactory = $this->createMock(BaseLinkerRequestFactory::class);
        $this->client = $this->createMock(BaseLinkerClient::class);
        $this->performanceLogger = $this->createMock(PerformanceLogger::class);

        $this->handler = new FetchMarketPlaceOrdersMessageHandler(
            $this->logger,
            $this->requestFactory,
            $this->client,
            $this->performanceLogger
        );
    }
    #[Test]
    public function handleWithNoOrders(): void
    {
        $message = new FetchMarketPlaceOrdersMessage(MarketPlaceEnum::ALLEGRO);

        $ordersRequest = $this->createMock(BaseLinkerRequest::class);
        $this->requestFactory
            ->expects($this->once())
            ->method('createGetOrdersRequest')
            ->with(MarketPlaceEnum::ALLEGRO)
            ->willReturn($ordersRequest);

        $this->client
            ->expects($this->once())
            ->method('request')
            ->with($ordersRequest)
            ->willReturn(['orders' => []]);

        $expectedMessages = [
            'Starting order synchronization',
            'No orders to synchronize'
        ];
        $callIndex = 0;

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('info')
            ->willReturnCallback(function ($message, $context) use ($expectedMessages, &$callIndex) {
                Assert::assertEquals($expectedMessages[$callIndex], $message);
                Assert::assertEquals('ALLEGRO', $context['marketplace']);
                $callIndex++;
            });


        ($this->handler)($message);
    }
    #[Test]
    public function handleWithOrders(): void
    {
        $message = new FetchMarketPlaceOrdersMessage(MarketPlaceEnum::AMAZON);

        $ordersRequest = $this->createMock(BaseLinkerRequest::class);
        $statusRequest = $this->createMock(BaseLinkerRequest::class);

        $this->requestFactory
            ->expects($this->once())
            ->method('createGetOrdersRequest')
            ->with(MarketPlaceEnum::AMAZON)
            ->willReturn($ordersRequest);

        $this->requestFactory
            ->expects($this->once())
            ->method('createGetOrderStatusListRequest')
            ->willReturn($statusRequest);

        $orders = [
            ['order_id' => 123, 'status_id' => 1],
            ['order_id' => 456, 'status_id' => 2],
        ];

        $statuses = [
            ['id' => 1, 'name' => 'New'],
            ['id' => 2, 'name' => 'Confirmed'],
        ];

        $this->client
            ->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                ['orders' => $orders],
                ['statuses' => $statuses]
            );

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('info');

        ($this->handler)($message);
    }
    #[Test]
    public function handleLogsDebugInformation(): void
    {
        $message = new FetchMarketPlaceOrdersMessage(MarketPlaceEnum::ALLEGRO);

        $ordersRequest = $this->createMock(BaseLinkerRequest::class);
        $statusRequest = $this->createMock(BaseLinkerRequest::class);

        $this->requestFactory
            ->method('createGetOrdersRequest')
            ->willReturn($ordersRequest);

        $this->requestFactory
            ->method('createGetOrderStatusListRequest')
            ->willReturn($statusRequest);

        $orders = [['order_id' => 789]];
        $statuses = [['id' => 1, 'name' => 'New']];

        $this->client
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                ['orders' => $orders],
                ['statuses' => $statuses]
            );

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('debug');

        ($this->handler)($message);
    }
}
