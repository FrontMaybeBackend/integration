<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Client\BaseLinkerClient;
use App\Enum\MarketPlaceEnum;
use App\Request\BaseLinkerRequest;
use App\Request\BaseLinkerRequestFactory;
use App\Services\OrderFetchService;
use App\Services\Paginator\BaseLinkerOrderPaginator;
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
    private BaseLinkerOrderPaginator $paginator;

    protected function setUp(): void
    {
        $this->client = $this->createMock(BaseLinkerClient::class);
        $this->requestFactory = $this->createMock(BaseLinkerRequestFactory::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->paginator = $this->createMock(BaseLinkerOrderPaginator::class);

        $this->service = new OrderFetchService(
            $this->client,
            $this->requestFactory,
            $this->logger,
            $this->paginator
        );
    }

    #[Test]
    public function fetchOrdersReturnsEmptyArrayWhenNoOrders(): void
    {
        $this->paginator
            ->expects($this->once())
            ->method('fetchAll')
            ->with(MarketPlaceEnum::ALLEGRO, null)
            ->willReturn([]);

        $this->logger
            ->expects($this->exactly(2))
            ->method('info');

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

        $this->paginator
            ->expects($this->once())
            ->method('fetchAll')
            ->with(MarketPlaceEnum::AMAZON, null)
            ->willReturn($orders);

        $result = $this->service->fetchOrders(MarketPlaceEnum::AMAZON);

        $this->assertSame($orders, $result);
        $this->assertCount(2, $result);
    }

    #[Test]
    public function fetchOrdersLogsDebugInformation(): void
    {
        $orders = [['order_id' => 1]];

        $this->paginator
            ->method('fetchAll')
            ->willReturn($orders);

        $loggedMessages = [];

        $this->logger
            ->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function ($message, $context) use (&$loggedMessages) {
                $loggedMessages[] = [
                    'message' => $message,
                    'marketplace' => $context['marketplace'] ?? null,
                    'total_orders' => $context['total_orders'] ?? null,
                ];
            });

        $this->service->fetchOrders(MarketPlaceEnum::ALLEGRO);

        $this->assertSame('Starting to fetch orders', $loggedMessages[0]['message']);
        $this->assertSame('ALLEGRO', $loggedMessages[0]['marketplace']);

        $this->assertSame('Finished fetching orders', $loggedMessages[1]['message']);
        $this->assertSame('ALLEGRO', $loggedMessages[1]['marketplace']);
        $this->assertSame(1, $loggedMessages[1]['total_orders']);
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

    #[Test]
    public function fetchOrdersDelegatesPaginationToPaginator(): void
    {
        $orders = array_fill(0, 250, ['order_id' => 1]);

        $this->paginator
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn($orders);

        $result = $this->service->fetchOrders(MarketPlaceEnum::AMAZON);

        $this->assertCount(250, $result);
    }
}
