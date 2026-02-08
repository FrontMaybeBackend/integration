<?php

declare(strict_types=1);

namespace App\Client;

use App\Request\BaseLinkerRequestInterface;

interface BaseLinkerClientInterface
{
    public function request(BaseLinkerRequestInterface $request): array;
}
