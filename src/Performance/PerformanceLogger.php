<?php

declare(strict_types=1);

namespace App\Performance;

use Psr\Log\LoggerInterface;

class PerformanceLogger
{
    private array $metrics = [];

    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }


    public function startMeasure(string $operationName): void
    {
        $this->metrics[$operationName] = [
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
        ];
    }


    public function endMeasure(string $operationName, bool $success = true, ?string $error = null): void
    {
        if (!isset($this->metrics[$operationName])) {
            return;
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $duration = $endTime - $this->metrics[$operationName]['start_time'];
        $memoryUsed = $endMemory - $this->metrics[$operationName]['start_memory'];

        $logData = [
            'operation' => $operationName,
            'duration_ms' => round($duration * 1000, 2),
            'memory_mb' => round($memoryUsed / 1024 / 1024, 2),
            'success' => $success,
        ];

        if ($error) {
            $logData['error'] = $error;
        }

        $this->logger->info('Performance metric', $logData);

        if ($duration > 2.0) {
            $this->logger->warning('Slow operation detected', $logData);
        }

        unset($this->metrics[$operationName]);
    }

    public function measure(string $operationName, callable $callback): mixed
    {
        $this->startMeasure($operationName);

        try {
            $result = $callback();
            $this->endMeasure($operationName, true);
            return $result;
        } catch (\Throwable $e) {
            $this->endMeasure($operationName, false, $e->getMessage());
            throw $e;
        }
    }
}
