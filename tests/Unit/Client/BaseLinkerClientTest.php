<?php

declare(strict_types=1);

namespace App\Tests\Unit\Client;

use App\Client\BaseLinkerClient;
use App\Request\BaseLinkerRequestInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[AllowMockObjectsWithoutExpectations]
class BaseLinkerClientTest extends KernelTestCase
{
    private LoggerInterface $logger;
    private HttpClientInterface $httpClient;
    private BaseLinkerClient $client;
    private string $apiKey;
    private string $apiUrl;

    protected function setUp(): void
    {
        $container = self::getContainer();
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->apiKey = $container->getParameter('baselinker_api_key');
        $this->apiUrl = $container->getParameter('baselinker_api_url');
        $this->client = new BaseLinkerClient(
            $this->logger,
            $this->httpClient,
            $this->apiKey,
            $this->apiUrl
        );
    }

    #[Test]
    public function requestSuccessful(): void
    {
        $request = $this->createMock(BaseLinkerRequestInterface::class);
        $request->method('getMethod')->willReturn('getOrders');
        $request->method('getParameters')->willReturn(['order_id' => 123]);

        $response = $this->createMock(ResponseInterface::class);
        $responseData = [
            'status' => 'SUCCESS',
            'orders' => [
                ['order_id' => 123, 'status' => 'confirmed']
            ]
        ];
        $response->method('toArray')->willReturn($responseData);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                $this->apiUrl,
                [
                    'headers' => ['X-BLTOKEN' => $this->apiKey],
                    'body' => [
                        'method' => 'getOrders',
                        'parameters' => json_encode(['order_id' => 123])
                    ]
                ]
            )
            ->willReturn($response);

        $this->logger
            ->expects($this->exactly(1))
            ->method('info');

        $result = $this->client->request($request);

        $this->assertEquals($responseData, $result);
    }

    #[Test]
    public function requestWithErrorStatus(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid configuration for BaseLinker API');

        $request = $this->createMock(BaseLinkerRequestInterface::class);
        $request->method('getMethod')->willReturn('getOrders');
        $request->method('getParameters')->willReturn([]);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'status' => 'ERROR',
            'error_code' => 'ERROR_INVALID_METHOD',
            'error_message' => 'Invalid method'
        ]);

        $this->httpClient
            ->method('request')
            ->willReturn($response);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('BaseLinker API returned ERROR', $this->anything());

        $this->client->request($request);
    }

    #[Test]
    public function requestWithHttpException(): void
    {
        $request = $this->createMock(BaseLinkerRequestInterface::class);
        $request->method('getMethod')->willReturn('getOrders');
        $request->method('getParameters')->willReturn([]);

        $exception = $this->createMock(HttpExceptionInterface::class);

        $this->httpClient
            ->method('request')
            ->willThrowException($exception);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('BaseLinker API HTTP error', $this->anything());

        $this->expectException(HttpExceptionInterface::class);

        $this->client->request($request);
    }

    #[Test]
    public function requestWithTransportException(): void
    {
        $request = $this->createMock(BaseLinkerRequestInterface::class);
        $request->method('getMethod')->willReturn('getOrders');
        $request->method('getParameters')->willReturn([]);

        $exception = $this->createMock(TransportExceptionInterface::class);

        $this->httpClient
            ->method('request')
            ->willThrowException($exception);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('BaseLinker API transport error', $this->anything());

        $this->expectException(TransportExceptionInterface::class);
        $this->client->request($request);
    }
}
