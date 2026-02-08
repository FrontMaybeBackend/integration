<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\MarketPlaceEnum;
use App\Message\FetchMarketPlaceOrdersMessage;
use App\Validator\MarketPlaceConfigurationValidator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

readonly class OrderSyncService
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private MarketPlaceConfigurationValidator $validator,
        private LoggerInterface $logger
    ) {
    }

    public function validateAndDispatchSync(MarketPlaceEnum $marketplace): void
    {
        $this->logger->info('Starting marketplace sync validation', [
            'marketplace' => $marketplace->value,
        ]);

        $this->validator->validate($marketplace);


        $this->dispatchSync($marketplace);

        $this->logger->info('Sync message dispatched successfully', [
            'marketplace' => $marketplace->value,
        ]);
    }

    private function dispatchSync(MarketPlaceEnum $marketplace): void
    {
        $message = new FetchMarketPlaceOrdersMessage($marketplace);
        $this->messageBus->dispatch($message);
    }
}
