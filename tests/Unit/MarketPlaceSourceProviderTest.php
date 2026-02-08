<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Enum\MarketPlaceEnum;
use App\MarketplaceSourceProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MarketPlaceSourceProviderTest extends TestCase
{
    #[Test]
    public function getSourceIdReturnsCorrectId(): void
    {
        $sources = [
            'allegro' => 12345,
            'amazon' => 67890,
        ];

        $provider = new MarketplaceSourceProvider($sources);

        $this->assertEquals(12345, $provider->getSourceId(MarketPlaceEnum::ALLEGRO));
        $this->assertEquals(67890, $provider->getSourceId(MarketPlaceEnum::AMAZON));
    }
    #[Test]
    public function getSourceIdReturnsNullForUnconfigured(): void
    {
        $sources = [
            'allegro' => 12345,
        ];

        $provider = new MarketplaceSourceProvider($sources);

        $this->assertNull($provider->getSourceId(MarketPlaceEnum::AMAZON));
    }
    #[Test]
    public function isConfiguredReturnsTrueForConfiguredMarketplace(): void
    {
        $sources = [
            'allegro' => 12345,
        ];

        $provider = new MarketplaceSourceProvider($sources);

        $this->assertTrue($provider->isConfigured(MarketPlaceEnum::ALLEGRO));
    }
    #[Test]
    public function isConfiguredReturnsFalseForUnconfiguredMarketplace(): void
    {
        $sources = [
            'allegro' => 12345,
        ];

        $provider = new MarketplaceSourceProvider($sources);

        $this->assertFalse($provider->isConfigured(MarketPlaceEnum::AMAZON));
    }
    #[Test]
    public function getSourceIdWithEmptySources(): void
    {
        $provider = new MarketplaceSourceProvider([]);

        $this->assertNull($provider->getSourceId(MarketPlaceEnum::ALLEGRO));
        $this->assertFalse($provider->isConfigured(MarketPlaceEnum::ALLEGRO));
    }
}
