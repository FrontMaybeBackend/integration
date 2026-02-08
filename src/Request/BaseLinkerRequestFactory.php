<?php

declare(strict_types=1);

namespace App\Request;

use App\Enum\BaseLinkerMethodEnum;
use App\Enum\MarketPlaceEnum;
use App\MarketplaceSourceProvider;

class BaseLinkerRequestFactory
{
    public function __construct(
        private readonly MarketplaceSourceProvider $marketplaceProvider
    ) {
    }

    public function createGetOrderSourcesRequest(): BaseLinkerRequest
    {
        return new BaseLinkerRequest(
            method: BaseLinkerMethodEnum::GET_ORDER_SOURCES->value,
            parameters: []
        );
    }

    public function createGetOrdersRequest(
        MarketPlaceEnum $marketplace,
        ?int $dateFrom = null
    ): BaseLinkerRequest {
        return new BaseLinkerRequest(
            method: BaseLinkerMethodEnum::GET_ORDERS->value,
            parameters: [
                'order_source_id' => $this->marketplaceProvider->getSourceId($marketplace),
                'date_confirmed_from' => $dateFrom ?? time() - 86400,
            ]
        );
    }


    public function createGetOrderStatusListRequest(): BaseLinkerRequest
    {
        return new BaseLinkerRequest(
            method: BaseLinkerMethodEnum::GET_ORDER_STATUS_LIST->value,
            parameters: []
        );
    }
}
