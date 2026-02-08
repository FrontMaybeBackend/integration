<?php

declare(strict_types=1);

namespace App\Tests\Unit\Performance;

use App\Performance\PerformanceLogger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PerformanceLoggerTest extends TestCase
{
    private LoggerInterface $logger;
    private PerformanceLogger $performanceLogger;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->performanceLogger = new PerformanceLogger($this->logger);
    }
    #[Test]
    public function startAndEndMeasure(): void
    {
        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Performance metric', $this->callback(function ($context) {
                return isset($context['operation'])
                    && $context['operation'] === 'test_operation'
                    && isset($context['duration_ms'])
                    && isset($context['memory_mb'])
                    && $context['success'] === true;
            }));

        $this->performanceLogger->startMeasure('test_operation');
        usleep(10000); // 10ms delay
        $this->performanceLogger->endMeasure('test_operation');
    }
    #[Test]
    public function measureWithSuccess(): void
    {
        $this->logger
            ->expects($this->once())
            ->method('info');

        $result = $this->performanceLogger->measure('operation', function () {
            return 'success';
        });

        $this->assertEquals('success', $result);
    }
    #[Test]
    public function measureWithException(): void
    {
        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Performance metric', $this->callback(function ($context) {
                return $context['success'] === false
                    && isset($context['error']);
            }));

        $this->expectException(\RuntimeException::class);

        $this->performanceLogger->measure('failing_operation', function () {
            throw new \RuntimeException('Test error');
        });
    }
    #[Test]
    public function slowOperationWarning(): void
    {
        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with('Slow operation detected', $this->anything());

        $this->performanceLogger->measure('slow_operation', function () {
            sleep(3); // 3 seconds
            return 'done';
        });
    }
    #[Test]
    public function endMeasureWithError(): void
    {
        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Performance metric', $this->callback(function ($context) {
                return $context['success'] === false
                    && $context['error'] === 'Something went wrong';
            }));

        $this->performanceLogger->startMeasure('operation');
        $this->performanceLogger->endMeasure('operation', false, 'Something went wrong');
    }
}
