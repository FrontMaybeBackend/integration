<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\MarketPlaceEnum;
use App\Exception\MarketPlaceNotConfiguredException;
use App\Services\OrderSyncService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:baselinker-integration',
    description: 'Launch integration with Baselinker to fetch orders from marketplaces'
)]
final class FetchOrderByMarketPlaceCommand extends Command
{
    public function __construct(
        private readonly OrderSyncService $orderSyncService,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('marketplace', InputArgument::REQUIRED, 'Marketplace to sync');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $marketplaceArg = strtoupper((string)$input->getArgument('marketplace'));


        $marketplace = MarketPlaceEnum::tryFrom($marketplaceArg);
        if (!$marketplace) {
            $output->writeln("<error>Invalid marketplace: {$marketplaceArg}</error>");
            $output->writeln(
                '<comment>Available: ' . implode(', ', array_column(MarketPlaceEnum::cases(), 'value')) . '</comment>'
            );
            return Command::FAILURE;
        }

        try {
            $this->orderSyncService->validateAndDispatchSync($marketplace);

            $output->writeln("<info>Successfully dispatched sync for {$marketplace->value}</info>");

            return Command::SUCCESS;
        } catch (MarketPlaceNotConfiguredException $e) {
            $this->logger->warning('Marketplace configuration validation failed', [
                'marketplace' => $marketplace->value,
                'reason' => $e->getMessage(),
            ]);

            $output->writeln("<error>{$e->getMessage()}</error>");

            return Command::FAILURE;
        }
    }
}
