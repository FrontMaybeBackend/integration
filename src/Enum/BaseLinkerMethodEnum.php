<?php

declare(strict_types=1);

namespace App\Enum;

enum BaseLinkerMethodEnum: string
{
    case GET_ORDERS = 'getOrders';
    case GET_ORDER_SOURCES = 'getOrderSources';
    case GET_ORDER_STATUS_LIST = 'getOrderStatusList';
}
