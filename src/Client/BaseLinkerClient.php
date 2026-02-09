<?php

declare(strict_types=1);

namespace App\Client;

use App\Request\BaseLinkerRequestInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class BaseLinkerClient implements BaseLinkerClientInterface
{
    public function __construct(
        private LoggerInterface $baselinkerLogger,
        private HttpClientInterface $client,
        private string $apiKey,
        private string $apiUrl
    ) {
    }

    public function request(BaseLinkerRequestInterface $request): array
    {
        $this->baselinkerLogger->info('BaseLinker API call', [
            'method' => $request->getMethod(),
            'parameters' => $request->getParameters(),
        ]);

        try {
            $response = $this->client->request('POST', $this->apiUrl, [
                'headers' => [
                    'X-BLTOKEN' => $this->apiKey,
                ],
                'body' => [
                    'method' => $request->getMethod(),
                    'parameters' => json_encode($request->getParameters()),
                ],
            ]);


            $data = $response->toArray();
        } catch (HttpExceptionInterface $e) {
            $this->baselinkerLogger->error('BaseLinker API HTTP error', [
                'method' => $request->getMethod(),
                'parameters' => $request->getParameters(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } catch (TransportExceptionInterface $e) {
            $this->baselinkerLogger->error('BaseLinker API transport error', [
                'method' => $request->getMethod(),
                'parameters' => $request->getParameters(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        $this->baselinkerLogger->debug('BaseLinker API response', $data);

        if (($data['status'] ?? null) === "ERROR") {
            $this->baselinkerLogger->error('BaseLinker API returned ERROR', $data);
            throw new \RuntimeException("Invalid configuration for BaseLinker API");
        }

        return $data;
    }
}
