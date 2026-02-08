<?php

declare(strict_types=1);

namespace App\Request;

class BaseLinkerRequest implements BaseLinkerRequestInterface
{
    public function __construct(
        private readonly string $method,
        private readonly array $parameters = []
    ) {
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }
}
