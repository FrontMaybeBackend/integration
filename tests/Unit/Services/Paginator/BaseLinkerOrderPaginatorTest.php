<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Paginator;

use App\Client\BaseLinkerClient;
use App\Enum\MarketPlaceEnum;
use App\Request\BaseLinkerRequest;
use App\Request\BaseLinkerRequestFactory;
use App\Services\Paginator\BaseLinkerOrderPaginator;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
class BaseLinkerOrderPaginatorTest extends TestCase
{
    private BaseLinkerClient $client;
    private BaseLinkerRequestFactory $requestFactory;
    private LoggerInterface $logger;
    private BaseLinkerOrderPaginator $paginator;

    protected function setUp(): void
    {
        $this->client = $this->createMock(BaseLinkerClient::class);
        $this->requestFactory = $this->createMock(BaseLinkerRequestFactory::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->paginator = new BaseLinkerOrderPaginator(
            $this->client,
            $this->requestFactory,
            $this->logger
        );
    }

    #[Test]
    public function fetchAllReturnsEmptyArrayWhenNoOrders(): void
    {
        $request = $this->createMock(BaseLinkerRequest::class);

        $this->requestFactory
            ->expects($this->once())
            ->method('createGetOrdersRequest')
            ->with(MarketPlaceEnum::ALLEGRO, null, null)
            ->willReturn($request);

        $this->client
            ->expects($this->once())
            ->method('request')
            ->with($request)
            ->willReturn(['orders' => []]);

        $result = $this->paginator->fetchAll(MarketPlaceEnum::ALLEGRO);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function fetchAllReturnsSinglePageWhenLessThan100Orders(): void
    {
        $orders = array_map(
            fn($i) => ['order_id' => $i, 'total' => 100.0],
            range(1, 75)
        );

        $request = $this->createMock(BaseLinkerRequest::class);

        $this->requestFactory
            ->expects($this->once())
            ->method('createGetOrdersRequest')
            ->willReturn($request);

        $this->client
            ->expects($this->once())
            ->method('request')
            ->with($request)
            ->willReturn(['orders' => $orders]);

        $result = $this->paginator->fetchAll(MarketPlaceEnum::AMAZON);

        $this->assertCount(75, $result);
        $this->assertSame($orders, $result);
    }

    #[Test]
    public function fetchAllHandlesPaginationWithMultiplePages(): void
    {

        $firstPage = array_map(
            fn($i) => ['order_id' => $i, 'total' => 100.0],
            range(1, 100)
        );


        $secondPage = array_map(
            fn($i) => ['order_id' => $i, 'total' => 100.0],
            range(101, 200)
        );


        $thirdPage = array_map(
            fn($i) => ['order_id' => $i, 'total' => 100.0],
            range(201, 250)
        );

        $request = $this->createMock(BaseLinkerRequest::class);

        $this->requestFactory
            ->expects($this->exactly(3))
            ->method('createGetOrdersRequest')
            ->willReturn($request);

        $this->client
            ->expects($this->exactly(3))
            ->method('request')
            ->with($request)
            ->willReturnOnConsecutiveCalls(
                ['orders' => $firstPage],
                ['orders' => $secondPage],
                ['orders' => $thirdPage]
            );

        $result = $this->paginator->fetchAll(MarketPlaceEnum::PERSONAL);

        $this->assertCount(250, $result);
    }

    #[Test]
    public function fetchAllPassesCorrectLastOrderIdBetweenPages(): void
    {

        $firstPage = array_fill(0, 97, ['order_id' => 50, 'total' => 100.0]);
        $firstPage[] = ['order_id' => 98, 'total' => 100.0];
        $firstPage[] = ['order_id' => 99, 'total' => 100.0];
        $firstPage[] = ['order_id' => 100, 'total' => 100.0];


        $secondPage = array_fill(0, 98, ['order_id' => 150, 'total' => 100.0]);
        $secondPage[] = ['order_id' => 199, 'total' => 100.0];
        $secondPage[] = ['order_id' => 200, 'total' => 100.0];


        $thirdPage = [
            ['order_id' => 201, 'total' => 100.0],
        ];

        $callCount = 0;
        $this->requestFactory
            ->expects($this->exactly(3))
            ->method('createGetOrdersRequest')
            ->willReturnCallback(function ($marketplace, $dateFrom, $idFrom) use (&$callCount) {
                $callCount++;

                if ($callCount === 1) {
                    $this->assertNull($idFrom, 'First request should have no idFrom');
                } elseif ($callCount === 2) {
                    $this->assertSame(100, $idFrom, 'Second request should use last order ID from first page');
                } elseif ($callCount === 3) {
                    $this->assertSame(200, $idFrom, 'Third request should use last order ID from second page');
                }

                return $this->createMock(BaseLinkerRequest::class);
            });

        $this->client
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                ['orders' => $firstPage],
                ['orders' => $secondPage],
                ['orders' => $thirdPage]
            );

        $result = $this->paginator->fetchAll(MarketPlaceEnum::ALLEGRO);
        $this->assertCount(201, $result);
    }

    #[Test]
    public function fetchAllStopsWhenExactly100OrdersReturned(): void
    {

        $orders = array_map(
            fn($i) => ['order_id' => $i, 'total' => 100.0],
            range(1, 100)
        );

        $request = $this->createMock(BaseLinkerRequest::class);

        $this->requestFactory
            ->expects($this->exactly(2))
            ->method('createGetOrdersRequest')
            ->willReturn($request);

        $this->client
            ->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                ['orders' => $orders],
                ['orders' => []]
            );

        $result = $this->paginator->fetchAll(MarketPlaceEnum::AMAZON);

        $this->assertCount(100, $result);
    }

    #[Test]
    public function fetchAllUsesProvidedDateFrom(): void
    {
        $customDateFrom = time() - (7 * 86400); // 7 dni do tyłu

        $this->requestFactory
            ->expects($this->once())
            ->method('createGetOrdersRequest')
            ->with(
                MarketPlaceEnum::ALLEGRO,
                $customDateFrom,
                null
            )
            ->willReturn($this->createMock(BaseLinkerRequest::class));

        $this->client
            ->method('request')
            ->willReturn(['orders' => []]);

        $this->paginator->fetchAll(MarketPlaceEnum::ALLEGRO, $customDateFrom);
    }

    #[Test]
    public function fetchAllLogsDebugInformation(): void
    {
        $orders = [['order_id' => 1, 'total' => 100.0]];

        $this->requestFactory
            ->method('createGetOrdersRequest')
            ->willReturn($this->createMock(BaseLinkerRequest::class));

        $this->client
            ->method('request')
            ->willReturn(['orders' => $orders]);

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('debug');

        $this->paginator->fetchAll(MarketPlaceEnum::PERSONAL);
    }

    #[Test]
    public function fetchAllHandlesMissingOrderIdInLastOrder(): void
    {
        $firstPage = [
            ['order_id' => 1],
            ['order_id' => 2],
            ['total' => 100.0],
        ];

        $firstPage = array_merge($firstPage, array_fill(0, 97, ['order_id' => 50]));

        $secondPage = [['order_id' => 101]];

        $this->requestFactory
            ->method('createGetOrdersRequest')
            ->willReturn($this->createMock(BaseLinkerRequest::class));

        $this->client
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                ['orders' => $firstPage],
                ['orders' => $secondPage]
            );


        $result = $this->paginator->fetchAll(MarketPlaceEnum::ALLEGRO);

        $this->assertCount(101, $result);
    }

    #[Test]
    public function fetchAllMergesOrdersCorrectly(): void
    {
        $firstPage = [
            ['order_id' => 1, 'customer' => 'Test1'],
            ['order_id' => 2, 'customer' => 'Test2'],
        ];
        $firstPage = array_merge($firstPage, array_fill(0, 98, ['order_id' => 50]));

        $secondPage = [
            ['order_id' => 101, 'customer' => 'Test3'],
        ];

        $this->requestFactory
            ->method('createGetOrdersRequest')
            ->willReturn($this->createMock(BaseLinkerRequest::class));

        $this->client
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                ['orders' => $firstPage],
                ['orders' => $secondPage]
            );

        $result = $this->paginator->fetchAll(MarketPlaceEnum::AMAZON);

        $this->assertCount(101, $result);
        $this->assertSame('Test1', $result[0]['customer']);
        $this->assertSame('Test2', $result[1]['customer']);
        $this->assertSame('Test3', $result[100]['customer']);
    }
}
