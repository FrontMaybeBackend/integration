<?php

declare(strict_types=1);

namespace App;

use App\Enum\MarketPlaceEnum;

readonly class MarketplaceSourceProvider
{
    public function __construct(private array $sources)
    {
    }

    public function getSourceId(MarketPlaceEnum $marketPlaceEnum): ?int
    {
        $key = strtolower($marketPlaceEnum->name);
        return isset($this->sources[$key]) ? (int)$this->sources[$key] : null;
    }

    public function isConfigured(MarketPlaceEnum $marketPlace): bool
    {
        return $this->getSourceId($marketPlace) !== null;
    }
}
