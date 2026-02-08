<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\MarketPlaceEnum;

class FetchMarketPlaceOrdersMessage
{
    public function __construct(
        private readonly MarketPlaceEnum $marketplace,
    ) {
    }

    public function getMarketPlace(): MarketPlaceEnum
    {
        return $this->marketplace;
    }
}
