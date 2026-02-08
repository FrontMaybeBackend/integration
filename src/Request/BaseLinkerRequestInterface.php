<?php

declare(strict_types=1);

namespace App\Request;

interface BaseLinkerRequestInterface
{
    public function getMethod();
    public function getParameters();
}
