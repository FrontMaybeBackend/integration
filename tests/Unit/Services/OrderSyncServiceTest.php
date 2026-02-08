<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Enum\MarketPlaceEnum;
use App\Exception\MarketPlaceNotConfiguredException;
use App\Message\FetchMarketPlaceOrdersMessage;
use App\Services\OrderSyncService;
use App\Validator\MarketPlaceConfigurationValidator;
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
    private MarketPlaceConfigurationValidator $validator;
    private LoggerInterface $logger;
    private OrderSyncService $service;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->validator = $this->createMock(MarketPlaceConfigurationValidator::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new OrderSyncService(
            $this->messageBus,
            $this->validator,
            $this->logger
        );
    }

    #[Test]
    public function validateAndDispatchSyncThrowsExceptionWhenValidationFails(): void
    {
        $this->expectException(MarketPlaceNotConfiguredException::class);
        $this->expectExceptionMessage('Marketplace ALLEGRO is not configured in Symfony services.');

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->with(MarketPlaceEnum::ALLEGRO)
            ->willThrowException(
                new MarketPlaceNotConfiguredException(
                    'Marketplace ALLEGRO is not configured in Symfony services.'
                )
            );

        $this->messageBus
            ->expects($this->never())
            ->method('dispatch');

        $this->service->validateAndDispatchSync(MarketPlaceEnum::ALLEGRO);
    }

    #[Test]
    public function validateAndDispatchSyncSuccessful(): void
    {
        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->with(MarketPlaceEnum::ALLEGRO);

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($message) {
                return $message instanceof FetchMarketPlaceOrdersMessage
                    && $message->getMarketPlace() === MarketPlaceEnum::ALLEGRO;
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $this->logger
            ->expects($this->exactly(2))
            ->method('info');

        $this->service->validateAndDispatchSync(MarketPlaceEnum::ALLEGRO);
    }

    #[Test]
    public function validateAndDispatchSyncLogsCorrectly(): void
    {
        $this->validator
            ->method('validate')
            ->with(MarketPlaceEnum::AMAZON);

        $this->messageBus
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $loggedMessages = [];

        $this->logger
            ->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function ($message, $context) use (&$loggedMessages) {
                $loggedMessages[] = [
                    'message' => $message,
                    'marketplace' => $context['marketplace'] ?? null,
                ];
            });

        $this->service->validateAndDispatchSync(MarketPlaceEnum::AMAZON);

        $this->assertSame('Starting marketplace sync validation', $loggedMessages[0]['message']);
        $this->assertSame('AMAZON', $loggedMessages[0]['marketplace']);

        $this->assertSame('Sync message dispatched successfully', $loggedMessages[1]['message']);
        $this->assertSame('AMAZON', $loggedMessages[1]['marketplace']);
    }

    #[Test]
    public function validateAndDispatchSyncDispatchesCorrectMessage(): void
    {
        $capturedMessage = null;

        $this->validator
            ->method('validate');

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($message) use (&$capturedMessage) {
                $capturedMessage = $message;
                return new Envelope($message);
            });

        $this->service->validateAndDispatchSync(MarketPlaceEnum::PERSONAL);

        $this->assertInstanceOf(FetchMarketPlaceOrdersMessage::class, $capturedMessage);
        $this->assertSame(MarketPlaceEnum::PERSONAL, $capturedMessage->getMarketPlace());
    }

    #[Test]
    public function validateAndDispatchSyncHandlesAllMarketplaces(): void
    {
        $marketplaces = [
            MarketPlaceEnum::ALLEGRO,
            MarketPlaceEnum::AMAZON,
            MarketPlaceEnum::PERSONAL,
        ];

        foreach ($marketplaces as $marketplace) {
            $validator = $this->createMock(MarketplaceConfigurationValidator::class);
            $validator
                ->expects($this->once())
                ->method('validate')
                ->with($marketplace);

            $messageBus = $this->createMock(MessageBusInterface::class);
            $messageBus
                ->expects($this->once())
                ->method('dispatch')
                ->with($this->callback(function ($message) use ($marketplace) {
                    return $message instanceof FetchMarketPlaceOrdersMessage
                        && $message->getMarketPlace() === $marketplace;
                }))
                ->willReturn(new Envelope(new \stdClass()));

            $logger = $this->createMock(LoggerInterface::class);

            $service = new OrderSyncService($messageBus, $validator, $logger);
            $service->validateAndDispatchSync($marketplace);
        }

        $this->assertTrue(true);
    }

    #[Test]
    public function validateAndDispatchSyncLogsStartValidation(): void
    {
        $this->validator
            ->method('validate');

        $this->messageBus
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $loggedStartValidation = false;

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('info')
            ->willReturnCallback(function ($message, $context) use (&$loggedStartValidation) {
                if ($message === 'Starting marketplace sync validation' && $context['marketplace'] === 'ALLEGRO') {
                    $loggedStartValidation = true;
                }
            });

        $this->service->validateAndDispatchSync(MarketPlaceEnum::ALLEGRO);

        $this->assertTrue($loggedStartValidation, 'Start validation message was not logged');
    }

    #[Test]
    public function validateAndDispatchSyncLogsSuccessfulDispatch(): void
    {
        $this->validator
            ->method('validate');

        $this->messageBus
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $loggedSuccessfulDispatch = false;

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('info')
            ->willReturnCallback(function ($message, $context) use (&$loggedSuccessfulDispatch) {
                if ($message === 'Sync message dispatched successfully' && $context['marketplace'] === 'AMAZON') {
                    $loggedSuccessfulDispatch = true;
                }
            });

        $this->service->validateAndDispatchSync(MarketPlaceEnum::AMAZON);

        $this->assertTrue($loggedSuccessfulDispatch, 'Successful dispatch message was not logged');
    }
}
