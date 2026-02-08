<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Client\BaseLinkerClient;
use App\Enum\MarketPlaceEnum;
use App\Request\BaseLinkerRequest;
use App\Request\BaseLinkerRequestFactory;
use App\Services\OrderFetchService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
class OrderFetchServiceTest extends TestCase
{
    private BaseLinkerClient $client;
    private BaseLinkerRequestFactory $requestFactory;
    private LoggerInterface $logger;
    private OrderFetchService $service;

    protected function setUp(): void
    {
        $this->client = $this->createMock(BaseLinkerClient::class);
        $this->requestFactory = $this->createMock(BaseLinkerRequestFactory::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new OrderFetchService(
            $this->client,
            $this->requestFactory,
            $this->logger
        );
    }

    #[Test]
    public function fetchOrdersReturnsEmptyArrayWhenNoOrders(): void
    {
        $request = $this->createMock(BaseLinkerRequest::class);

        $this->requestFactory
            ->expects($this->once())
            ->method('createGetOrdersRequest')
            ->with(MarketPlaceEnum::ALLEGRO)
            ->willReturn($request);

        $this->client
            ->expects($this->once())
            ->method('request')
            ->with($request)
            ->willReturn(['orders' => []]);

        $this->logger
            ->expects($this->exactly(2))
            ->method('debug')
            ->willReturnCallback(function (string $message, array $context) {
                static $call = 0;

                if ($call === 0) {
                    $this->assertSame('Fetching orders from BaseLinker', $message);
                    $this->assertSame(['marketplace' => 'ALLEGRO'], $context);
                }

                if ($call === 1) {
                    $this->assertSame('Orders fetched', $message);
                    $this->assertSame([
                        'marketplace' => 'ALLEGRO',
                        'count' => 0,
                    ], $context);
                }

                $call++;
            });


        $result = $this->service->fetchOrders(MarketPlaceEnum::ALLEGRO);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function fetchOrdersReturnsOrdersArray(): void
    {
        $orders = [
            ['order_id' => 123, 'total' => 100.50],
            ['order_id' => 456, 'total' => 200.75],
        ];

        $request = $this->createMock(BaseLinkerRequest::class);

        $this->requestFactory
            ->method('createGetOrdersRequest')
            ->with(MarketPlaceEnum::AMAZON)
            ->willReturn($request);

        $this->client
            ->method('request')
            ->with($request)
            ->willReturn(['orders' => $orders]);

        $result = $this->service->fetchOrders(MarketPlaceEnum::AMAZON);

        $this->assertSame($orders, $result);
        $this->assertCount(2, $result);
    }

    #[Test]
    public function fetchOrdersLogsDebugInformation(): void
    {
        $request = $this->createMock(BaseLinkerRequest::class);

        $this->requestFactory
            ->method('createGetOrdersRequest')
            ->willReturn($request);

        $this->client
            ->method('request')
            ->willReturn(['orders' => [['order_id' => 1]]]);

        $this->logger
            ->expects($this->exactly(2))
            ->method('debug');

        $this->service->fetchOrders(MarketPlaceEnum::PERSONAL);
    }

    #[Test]
    public function fetchOrderStatusesReturnsEmptyArrayWhenNoStatuses(): void
    {
        $request = $this->createMock(BaseLinkerRequest::class);

        $this->requestFactory
            ->expects($this->once())
            ->method('createGetOrderStatusListRequest')
            ->willReturn($request);

        $this->client
            ->expects($this->once())
            ->method('request')
            ->with($request)
            ->willReturn(['statuses' => []]);

        $this->logger
            ->expects($this->exactly(2))
            ->method('debug')
            ->willReturnCallback(function (...$args) {
                static $call = 0;

                if ($call === 0) {
                    $this->assertSame('Fetching order statuses', $args[0]);
                }

                if ($call === 1) {
                    $this->assertSame('Order statuses fetched', $args[0]);
                    $this->assertSame(['count' => 0], $args[1]);
                }

                $call++;
            });


        $result = $this->service->fetchOrderStatuses();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function fetchOrderStatusesReturnsStatusesArray(): void
    {
        $statuses = [
            ['id' => 1, 'name' => 'New'],
            ['id' => 2, 'name' => 'Processing'],
            ['id' => 3, 'name' => 'Shipped'],
        ];

        $request = $this->createMock(BaseLinkerRequest::class);

        $this->requestFactory
            ->method('createGetOrderStatusListRequest')
            ->willReturn($request);

        $this->client
            ->method('request')
            ->with($request)
            ->willReturn(['statuses' => $statuses]);

        $result = $this->service->fetchOrderStatuses();

        $this->assertSame($statuses, $result);
        $this->assertCount(3, $result);
    }

    #[Test]
    public function fetchOrderStatusesLogsDebugInformation(): void
    {
        $request = $this->createMock(BaseLinkerRequest::class);

        $this->requestFactory
            ->method('createGetOrderStatusListRequest')
            ->willReturn($request);

        $this->client
            ->method('request')
            ->willReturn(['statuses' => [['id' => 1]]]);

        $this->logger
            ->expects($this->exactly(2))
            ->method('debug');

        $this->service->fetchOrderStatuses();
    }

    #[Test]
    public function fetchOrdersHandlesMissingOrdersKey(): void
    {
        $request = $this->createMock(BaseLinkerRequest::class);

        $this->requestFactory
            ->method('createGetOrdersRequest')
            ->willReturn($request);

        $this->client
            ->method('request')
            ->willReturn(['status' => 'SUCCESS']);

        $result = $this->service->fetchOrders(MarketPlaceEnum::ALLEGRO);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function fetchOrderStatusesHandlesMissingStatusesKey(): void
    {
        $request = $this->createMock(BaseLinkerRequest::class);

        $this->requestFactory
            ->method('createGetOrderStatusListRequest')
            ->willReturn($request);

        $this->client
            ->method('request')
            ->willReturn(['status' => 'SUCCESS']);

        $result = $this->service->fetchOrderStatuses();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
