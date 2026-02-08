<?php

declare(strict_types=1);

namespace App\Validator;

use App\Client\BaseLinkerClient;
use App\Enum\MarketPlaceEnum;
use App\Exception\MarketPlaceNotConfiguredException;
use App\MarketplaceSourceProvider;
use App\Request\BaseLinkerRequestFactory;
use Psr\Log\LoggerInterface;

readonly class MarketPlaceConfigurationValidator
{
    public function __construct(
        private MarketplaceSourceProvider $marketplaceProvider,
        private BaseLinkerClient $client,
        private BaseLinkerRequestFactory $requestFactory,
        private LoggerInterface $logger
    ) {
    }

    public function validate(MarketPlaceEnum $marketplace): void
    {
        $this->validateSymfonyConfiguration($marketplace);
        $this->validateBaseLinkerConfiguration($marketplace);
    }

    private function validateSymfonyConfiguration(MarketPlaceEnum $marketplace): void
    {
        if (!$this->marketplaceProvider->isConfigured($marketplace)) {
            throw new MarketPlaceNotConfiguredException(
                sprintf(
                    "Marketplace %s is not configured in Symfony services.",
                    $marketplace->value,
                )
            );
        }

        $this->logger->debug('Symfony configuration valid', [
            'marketplace' => $marketplace->value,
        ]);
    }

    private function validateBaseLinkerConfiguration(MarketPlaceEnum $marketplace): void
    {
        $configuredSourceId = $this->marketplaceProvider->getSourceId($marketplace);
        $request = $this->requestFactory->createGetOrderSourcesRequest();
        $response = $this->client->request($request);

        $sources = $response['sources'] ?? [];

        if (empty($sources)) {
            throw new MarketPlaceNotConfiguredException(
                'No order sources configured in BaseLinker'
            );
        }

        $sourceExists = $this->sourceIdExistsInBaseLinker(
            $configuredSourceId,
            $sources,
            $marketplace->name
        );

        if (!$sourceExists) {
            throw new MarketPlaceNotConfiguredException(
                sprintf(
                    "Marketplace %s is configured in Symfony, but doesn't exist in BaseLinker.",
                    $marketplace->value,
                )
            );
        }

        $this->logger->debug('BaseLinker configuration valid', [
            'marketplace' => $marketplace->value,
            'source_id' => $configuredSourceId,
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
}
