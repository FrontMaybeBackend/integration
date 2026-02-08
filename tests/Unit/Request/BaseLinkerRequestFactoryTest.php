<?php

declare(strict_types=1);

namespace App\Tests\Unit\Request;

use App\Enum\BaseLinkerMethodEnum;
use App\Enum\MarketPlaceEnum;
use App\MarketplaceSourceProvider;
use App\Request\BaseLinkerRequestFactory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class BaseLinkerRequestFactoryTest extends TestCase
{
    private MarketplaceSourceProvider $marketplaceProvider;
    private BaseLinkerRequestFactory $factory;

    protected function setUp(): void
    {
        $this->marketplaceProvider = $this->createMock(MarketplaceSourceProvider::class);
        $this->factory = new BaseLinkerRequestFactory($this->marketplaceProvider);
    }
    #[Test]
    public function createGetOrderSourcesRequest(): void
    {
        $request = $this->factory->createGetOrderSourcesRequest();

        $this->assertEquals(BaseLinkerMethodEnum::GET_ORDER_SOURCES->value, $request->getMethod());
        $this->assertEquals([], $request->getParameters());
    }
    #[Test]
    public function createGetOrdersRequestWithDefaults(): void
    {
        $this->marketplaceProvider
            ->expects($this->once())
            ->method('getSourceId')
            ->with(MarketPlaceEnum::ALLEGRO)
            ->willReturn(12345);

        $beforeTime = time() - 86400;
        $request = $this->factory->createGetOrdersRequest(MarketPlaceEnum::ALLEGRO);
        $afterTime = time() - 86400;

        $this->assertEquals(BaseLinkerMethodEnum::GET_ORDERS->value, $request->getMethod());

        $params = $request->getParameters();
        $this->assertEquals(12345, $params['order_source_id']);
        $this->assertGreaterThanOrEqual($beforeTime, $params['date_confirmed_from']);
        $this->assertLessThanOrEqual($afterTime, $params['date_confirmed_from']);
    }
    #[Test]
    public function createGetOrdersRequestWithCustomDate(): void
    {
        $customDate = 1640000000;

        $this->marketplaceProvider
            ->expects($this->once())
            ->method('getSourceId')
            ->with(MarketPlaceEnum::AMAZON)
            ->willReturn(67890);

        $request = $this->factory->createGetOrdersRequest(MarketPlaceEnum::AMAZON, $customDate);

        $params = $request->getParameters();
        $this->assertEquals(67890, $params['order_source_id']);
        $this->assertEquals($customDate, $params['date_confirmed_from']);
    }
    #[Test]
    public function createGetOrderStatusListRequest(): void
    {
        $request = $this->factory->createGetOrderStatusListRequest();

        $this->assertEquals(BaseLinkerMethodEnum::GET_ORDER_STATUS_LIST->value, $request->getMethod());
        $this->assertEquals([], $request->getParameters());
    }
}
