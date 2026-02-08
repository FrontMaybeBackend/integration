<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Client\BaseLinkerClient;
use App\Enum\MarketPlaceEnum;
use App\Exception\MarketPlaceNotConfiguredException;
use App\MarketplaceSourceProvider;
use App\Message\FetchMarketPlaceOrdersMessage;
use App\Request\BaseLinkerRequest;
use App\Request\BaseLinkerRequestFactory;
use App\Services\OrderSyncService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[AllowMockObjectsWithoutExpectations]
class OrderSyncServiceTest extends TestCase
{
    private MessageBusInterface $messageBus;
    private MarketplaceSourceProvider $marketplaceProvider;
    private BaseLinkerClient $client;
    private BaseLinkerRequestFactory $requestFactory;
    private LoggerInterface $logger;
    private OrderSyncService $service;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->marketplaceProvider = $this->createMock(MarketplaceSourceProvider::class);
        $this->client = $this->createMock(BaseLinkerClient::class);
        $this->requestFactory = $this->createMock(BaseLinkerRequestFactory::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new OrderSyncService(
            $this->messageBus,
            $this->marketplaceProvider,
            $this->client,
            $this->requestFactory,
            $this->logger
        );
    }

    #[Test]
    public function validateAndDispatchSyncThrowsExceptionWhenNotConfiguredInSymfony(): void
    {
        $this->expectException(MarketPlaceNotConfiguredException::class);
        $this->expectExceptionMessage('Marketplace ALLEGRO is not configured in Symfony services.');

        $this->marketplaceProvider
            ->expects($this->once())
            ->method('isConfigured')
            ->with(MarketPlaceEnum::ALLEGRO)
            ->willReturn(false);

        $this->marketplaceProvider
            ->expects($this->once())
            ->method('getSourceId')
            ->with(MarketPlaceEnum::ALLEGRO)
            ->willReturn(null);

        $this->service->validateAndDispatchSync(MarketPlaceEnum::ALLEGRO);
    }

    #[Test]
    public function validateAndDispatchSyncThrowsExceptionWhenNoSourcesInBaseLinker(): void
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
            ->method('createGetOrderSourcesRequest')
            ->willReturn($request);

        $this->client
            ->expects($this->once())
            ->method('request')
            ->with($request)
            ->willReturn(['sources' => []]);

        $this->service->validateAndDispatchSync(MarketPlaceEnum::ALLEGRO);
    }
    #[Test]
    public function validateAndDispatchSyncThrowsExceptionWhenSourceNotInBaseLinker(): void
    {
        $this->expectException(MarketPlaceNotConfiguredException::class);
        $this->expectExceptionMessage("Marketplace ALLEGRO is configured in Symfony, but it doesn't exist in BaseLinker.");

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

        $this->service->validateAndDispatchSync(MarketPlaceEnum::ALLEGRO);
    }
    #[Test]
    public function validateAndDispatchSyncSuccessful(): void
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
            ->expects($this->once())
            ->method('request')
            ->willReturn([
                'sources' => [
                    'allegro' => [
                        '12345' => 'Allegro PL',
                    ]
                ]
            ]);

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($message) {
                return $message instanceof FetchMarketPlaceOrdersMessage
                    && $message->getMarketPlace() === MarketPlaceEnum::ALLEGRO;
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('info');

        $this->service->validateAndDispatchSync(MarketPlaceEnum::ALLEGRO);
    }
    #[Test]
    public function syncOrder(): void
    {
        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($message) {
                return $message instanceof FetchMarketPlaceOrdersMessage
                    && $message->getMarketPlace() === MarketPlaceEnum::AMAZON;
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $this->service->syncOrder(MarketPlaceEnum::AMAZON);
    }
}
