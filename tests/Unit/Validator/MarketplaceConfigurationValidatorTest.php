<?php

declare(strict_types=1);

namespace App\Tests\Unit\Validator;

use App\Client\BaseLinkerClient;
use App\Enum\MarketPlaceEnum;
use App\Exception\MarketPlaceNotConfiguredException;
use App\MarketplaceSourceProvider;
use App\Request\BaseLinkerRequest;
use App\Request\BaseLinkerRequestFactory;
use App\Validator\MarketplaceConfigurationValidator;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
class MarketplaceConfigurationValidatorTest extends TestCase
{
    private MarketplaceSourceProvider $marketplaceProvider;
    private BaseLinkerClient $client;
    private BaseLinkerRequestFactory $requestFactory;
    private LoggerInterface $logger;
    private MarketplaceConfigurationValidator $validator;

    protected function setUp(): void
    {
        $this->marketplaceProvider = $this->createMock(MarketplaceSourceProvider::class);
        $this->client = $this->createMock(BaseLinkerClient::class);
        $this->requestFactory = $this->createMock(BaseLinkerRequestFactory::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->validator = new MarketplaceConfigurationValidator(
            $this->marketplaceProvider,
            $this->client,
            $this->requestFactory,
            $this->logger
        );
    }

    #[Test]
    public function validateThrowsExceptionWhenNotConfiguredInSymfony(): void
    {
        $this->expectException(MarketPlaceNotConfiguredException::class);
        $this->expectExceptionMessage('Marketplace ALLEGRO is not configured in Symfony services.');

        $this->marketplaceProvider
            ->expects($this->once())
            ->method('isConfigured')
            ->with(MarketPlaceEnum::ALLEGRO)
            ->willReturn(false);

        $this->client
            ->expects($this->never())
            ->method('request');

        $this->validator->validate(MarketPlaceEnum::ALLEGRO);
    }

    #[Test]
    public function validateThrowsExceptionWhenNoSourcesInBaseLinker(): void
    {
        $this->expectException(MarketPlaceNotConfiguredException::class);
        $this->expectExceptionMessage('No order sources configured in BaseLinker');

        $this->marketplaceProvider
            ->method('isConfigured')
            ->willReturn(true);

        $this->marketplaceProvider
            ->method('getSourceId')
            ->willReturn(12345);

        $request = $this->createMock(BaseLinkerRequest::class);
        $this->requestFactory
            ->expects($this->once())
            ->method('createGetOrderSourcesRequest')
            ->willReturn($request);

        $this->client
            ->expects($this->once())
            ->method('request')
            ->with($request)
            ->willReturn(['sources' => []]);

        $this->validator->validate(MarketPlaceEnum::ALLEGRO);
    }

    #[Test]
    public function validateThrowsExceptionWhenSourceIdNotFoundInBaseLinker(): void
    {
        $this->expectException(MarketPlaceNotConfiguredException::class);
        $this->expectExceptionMessage(
            "Marketplace ALLEGRO is configured in Symfony, but doesn't exist in BaseLinker."
        );

        $this->marketplaceProvider
            ->method('isConfigured')
            ->willReturn(true);

        $this->marketplaceProvider
            ->method('getSourceId')
            ->willReturn(99999);

        $request = $this->createMock(BaseLinkerRequest::class);
        $this->requestFactory
            ->method('createGetOrderSourcesRequest')
            ->willReturn($request);

        $this->client
            ->expects($this->once())
            ->method('request')
            ->willReturn([
                'sources' => [
                    'allegro' => [
                        '12345' => 'Allegro PL',
                        '67890' => 'Allegro CZ',
                    ]
                ]
            ]);

        $this->validator->validate(MarketPlaceEnum::ALLEGRO);
    }

    #[Test]
    public function validateSuccessfullyWhenConfigurationIsValid(): void
    {
        $this->marketplaceProvider
            ->expects($this->once())
            ->method('isConfigured')
            ->with(MarketPlaceEnum::ALLEGRO)
            ->willReturn(true);

        $this->marketplaceProvider
            ->expects($this->atLeastOnce())
            ->method('getSourceId')
            ->with(MarketPlaceEnum::ALLEGRO)
            ->willReturn(12345);

        $request = $this->createMock(BaseLinkerRequest::class);
        $this->requestFactory
            ->expects($this->once())
            ->method('createGetOrderSourcesRequest')
            ->willReturn($request);

        $this->client
            ->expects($this->once())
            ->method('request')
            ->with($request)
            ->willReturn([
                'sources' => [
                    'allegro' => [
                        '12345' => 'Allegro PL',
                        '67890' => 'Allegro CZ',
                    ]
                ]
            ]);

        $this->logger
            ->expects($this->exactly(2))
            ->method('debug')
            ->willReturnCallback(function (string $message, array $context) {
                static $call = 0;

                if ($call === 0) {
                    $this->assertSame('Symfony configuration valid', $message);
                    $this->assertSame(
                        ['marketplace' => 'ALLEGRO'],
                        $context
                    );
                }

                if ($call === 1) {
                    $this->assertSame('BaseLinker configuration valid', $message);
                    $this->assertSame(
                        [
                            'marketplace' => 'ALLEGRO',
                            'source_id' => 12345,
                        ],
                        $context
                    );
                }

                $call++;
            });


        $this->validator->validate(MarketPlaceEnum::ALLEGRO);
    }

    #[Test]
    public function validateHandlesDifferentMarketplaces(): void
    {
        $testCases = [
            [
                'marketplace' => MarketPlaceEnum::ALLEGRO,
                'sourceId' => 11111,
                'sources' => ['allegro' => ['11111' => 'Allegro']],
            ],
            [
                'marketplace' => MarketPlaceEnum::AMAZON,
                'sourceId' => 22222,
                'sources' => ['amazon' => ['22222' => 'Amazon']],
            ],
            [
                'marketplace' => MarketPlaceEnum::PERSONAL,
                'sourceId' => 33333,
                'sources' => ['personal' => ['33333' => 'Personal Store']],
            ],
        ];

        foreach ($testCases as $testCase) {
            $provider = $this->createMock(MarketplaceSourceProvider::class);
            $provider->method('isConfigured')->willReturn(true);
            $provider->method('getSourceId')->willReturn($testCase['sourceId']);

            $client = $this->createMock(BaseLinkerClient::class);
            $client->method('request')->willReturn(['sources' => $testCase['sources']]);

            $factory = $this->createMock(BaseLinkerRequestFactory::class);
            $factory->method('createGetOrderSourcesRequest')
                ->willReturn($this->createMock(BaseLinkerRequest::class));

            $validator = new MarketplaceConfigurationValidator(
                $provider,
                $client,
                $factory,
                $this->logger
            );

            $validator->validate($testCase['marketplace']);
        }

        $this->assertTrue(true);
    }

    #[Test]
    public function validateLogsDebugInformation(): void
    {
        $this->marketplaceProvider
            ->method('isConfigured')
            ->willReturn(true);

        $this->marketplaceProvider
            ->method('getSourceId')
            ->willReturn(12345);

        $request = $this->createMock(BaseLinkerRequest::class);
        $this->requestFactory
            ->method('createGetOrderSourcesRequest')
            ->willReturn($request);

        $this->client
            ->method('request')
            ->willReturn([
                'sources' => [
                    'amazon' => [
                        '12345' => 'Amazon Store',
                    ]
                ]
            ]);

        $this->logger
            ->expects($this->exactly(2))
            ->method('debug');

        $this->validator->validate(MarketPlaceEnum::AMAZON);
    }

    #[Test]
    public function validateHandlesNullSourceId(): void
    {
        $this->expectException(MarketPlaceNotConfiguredException::class);

        $this->marketplaceProvider
            ->method('isConfigured')
            ->willReturn(true);

        $this->marketplaceProvider
            ->method('getSourceId')
            ->willReturn(null);

        $request = $this->createMock(BaseLinkerRequest::class);
        $this->requestFactory
            ->method('createGetOrderSourcesRequest')
            ->willReturn($request);

        $this->client
            ->method('request')
            ->willReturn([
                'sources' => [
                    'allegro' => [
                        '12345' => 'Allegro',
                    ]
                ]
            ]);

        $this->validator->validate(MarketPlaceEnum::ALLEGRO);
    }

    #[Test]
    public function validateHandlesMissingMarketplaceKeyInSources(): void
    {
        $this->expectException(MarketPlaceNotConfiguredException::class);
        $this->expectExceptionMessage(
            "Marketplace ALLEGRO is configured in Symfony, but doesn't exist in BaseLinker."
        );

        $this->marketplaceProvider
            ->method('isConfigured')
            ->willReturn(true);

        $this->marketplaceProvider
            ->method('getSourceId')
            ->willReturn(12345);

        $request = $this->createMock(BaseLinkerRequest::class);
        $this->requestFactory
            ->method('createGetOrderSourcesRequest')
            ->willReturn($request);

        $this->client
            ->method('request')
            ->willReturn([
                'sources' => [
                    'amazon' => [
                        '99999' => 'Amazon',
                    ]
                ]
            ]);

        $this->validator->validate(MarketPlaceEnum::ALLEGRO);
    }
}
