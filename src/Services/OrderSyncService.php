<?php

declare(strict_types=1);

namespace App\Services;

use App\Client\BaseLinkerClient;
use App\Enum\MarketPlaceEnum;
use App\Exception\MarketPlaceNotConfiguredException;
use App\MarketplaceSourceProvider;
use App\Message\FetchMarketPlaceOrdersMessage;
use App\Request\BaseLinkerRequestFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class OrderSyncService
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly MarketplaceSourceProvider $marketplaceSourceProvider,
        private readonly BaseLinkerClient $client,
        private readonly BaseLinkerRequestFactory $requestFactory,
        private readonly LoggerInterface $logger
    ) {
    }


    public function validateAndDispatchSync(MarketPlaceEnum $marketplace): void
    {
        $this->logger->info('Validating marketplace configuration', [
            'marketplace' => $marketplace->value,
        ]);

        if (!$this->marketplaceSourceProvider->isConfigured($marketplace)) {
            $sourceId = $this->marketplaceSourceProvider->getSourceId($marketplace);

            throw new MarketPlaceNotConfiguredException(
                sprintf(
                    "Marketplace %s is not configured in Symfony services.",
                    $marketplace->value,
                )
            );
        }

        $this->validateBaseLinkerConfiguration($marketplace);

        $this->syncOrder($marketplace);

        $this->logger->info('Sync message dispatched successfully', [
            'marketplace' => $marketplace->value,
            'source_id' => $this->marketplaceSourceProvider->getSourceId($marketplace),
        ]);
    }


    private function validateBaseLinkerConfiguration(MarketPlaceEnum $marketplace): void
    {
        $configuredSourceId = $this->marketplaceSourceProvider->getSourceId($marketplace);
        $request = $this->requestFactory->createGetOrderSourcesRequest();
        $response = $this->client->request($request);

        $sources = $response['sources'] ?? [];

        if (empty($sources)) {
            throw new MarketPlaceNotConfiguredException(
                'No order sources configured in BaseLinker'
            );
        }

        $sourceExists = $this->sourceIdExistsInBaseLinker($configuredSourceId, $sources, $marketplace->name);

        if (!$sourceExists) {
            throw new MarketPlaceNotConfiguredException(
                sprintf(
                    "Marketplace %s is configured in Symfony, but it doesn't exist in BaseLinker.",
                    $marketplace->value,
                )
            );
        }

        $this->logger->debug('BaseLinker configuration validated successfully', [
            'marketplace' => $marketplace->value,
            'source_id' => $configuredSourceId,
            'total_sources' => count($sources),
        ]);
    }


    private function sourceIdExistsInBaseLinker(?int $sourceId, array $sources, string $marketplace): bool
    {
        if ($sourceId === null || empty($sources)) {
            return false;
        }

        $marketplace = strtolower($marketplace);

        if (!isset($sources[$marketplace]) || !is_array($sources[$marketplace])) {
            return false;
        }

        foreach ($sources[$marketplace] as $idStr => $name) {
            if ((int)$idStr === $sourceId) {
                return true;
            }
        }

        return false;
    }


    public function syncOrder(MarketPlaceEnum $marketplace): void
    {
        $message = new FetchMarketPlaceOrdersMessage($marketplace);
        $this->messageBus->dispatch($message);
    }
}
