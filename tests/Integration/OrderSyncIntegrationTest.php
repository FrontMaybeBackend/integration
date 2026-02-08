<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Client\BaseLinkerClient;
use App\Enum\MarketPlaceEnum;
use App\MarketplaceSourceProvider;
use App\Message\FetchMarketPlaceOrdersMessage;
use App\MessageHandler\FetchMarketPlaceOrdersMessageHandler;
use App\Performance\PerformanceLogger;
use App\Request\BaseLinkerRequestFactory;
use App\Services\OrderSyncService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[AllowMockObjectsWithoutExpectations]
class OrderSyncIntegrationTest extends TestCase
{
    private MockHttpClient $httpClient;
    private BaseLinkerClient $baseLinkerClient;
    private MarketplaceSourceProvider $marketplaceProvider;
    private BaseLinkerRequestFactory $requestFactory;
    private OrderSyncService $orderSyncService;
    private FetchMarketPlaceOrdersMessageHandler $messageHandler;

    private PerformanceLogger $performanceLogger;

    protected function setUp(): void
    {
        $this->httpClient = new MockHttpClient();
        $logger = $this->createMock(LoggerInterface::class);
        $this->performanceLogger = new PerformanceLogger($logger);
        $this->baseLinkerClient = new BaseLinkerClient(
            new NullLogger(),
            $this->httpClient,
            'test-api-key',
            'https://api.baselinker.com/connector.php'
        );

        $this->marketplaceProvider = new MarketplaceSourceProvider([
            'allegro' => 12345,
            'amazon' => 67890,
        ]);

        $this->requestFactory = new BaseLinkerRequestFactory($this->marketplaceProvider);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $this->orderSyncService = new OrderSyncService(
            $messageBus,
            $this->marketplaceProvider,
            $this->baseLinkerClient,
            $this->requestFactory,
            new NullLogger()
        );

        $this->messageHandler = new FetchMarketPlaceOrdersMessageHandler(
            new NullLogger(),
            $this->requestFactory,
            $this->baseLinkerClient,
            $this->performanceLogger
        );
    }
    #[Test]
    public function completeOrderSyncFlow(): void
    {
        $responses = [
            new MockResponse(json_encode([
                'status' => 'SUCCESS',
                'sources' => [
                    'allegro' => [
                        '12345' => 'Allegro account',
                    ]
                ]
            ])),

            new MockResponse(json_encode([
                'status' => 'SUCCESS',
                'orders' => [
                    [
                        'order_id' => 1630473,
                        'shop_order_id' => 2824,
                        'external_order_id' => '534534234',
                        'order_source' => 'allegro',
                        'order_source_id' => 12345,
                        'order_source_info' => '-',
                        'order_status_id' => 6624,
                        'date_add' => time(),
                        'date_confirmed' => time(),
                        'user_login' => 'nick123',
                        'phone' => '693123123',
                        'email' => 'test@test.com',
                        'user_comments' => 'User comment',
                        'admin_comments' => 'Seller test comments',
                        'currency' => 'GBP',
                        'payment_method' => 'PayPal',
                        'payment_done' => '50',
                        'products' => [
                            [
                                'storage' => 'shop',
                                'storage_id' => 1,
                                'order_product_id' => 154904741,
                                'product_id' => '5434',
                                'variant_id' => 52124,
                                'name' => 'Harry Potter and the Chamber of Secrets',
                                'attributes' => 'Colour: green',
                                'sku' => 'LU4235',
                                'ean' => '1597368451236',
                                'location' => 'A1-13-7',
                                'price_brutto' => 20.00,
                                'tax_rate' => 23,
                                'quantity' => 2,
                                'weight' => 1,
                                'bundle_id' => 0
                            ]
                        ]
                    ]
                ]
            ])),
            new MockResponse(json_encode([
                'status' => 'SUCCESS',
                'statuses' => [
                    ['id' => 1051, 'name' => 'New orders', 'name_for_customer' => 'Order accepted'],
                    ['id' => 1052, 'name' => 'To be paid (courier)', 'name_for_customer' => 'Awaiting payment'],
                ]
            ]))
        ];

        $this->httpClient->setResponseFactory($responses);

        $this->orderSyncService->validateAndDispatchSync(MarketPlaceEnum::ALLEGRO);

        $message = new FetchMarketPlaceOrdersMessage(MarketPlaceEnum::ALLEGRO);
        ($this->messageHandler)($message);

        $this->assertEquals(3, $this->httpClient->getRequestsCount());

    }
    #[Test]
    public function orderSyncWithEmptyOrders(): void
    {
        $responses = [
            new MockResponse(json_encode([
                'status' => 'SUCCESS',
                'sources' => [
                    'amazon' => [
                        '67890' => 'Amazon EU',
                    ]
                ]
            ])),
            new MockResponse(json_encode([
                'status' => 'SUCCESS',
                'orders' => []
            ])),
        ];

        $this->httpClient->setResponseFactory($responses);

        $this->orderSyncService->validateAndDispatchSync(MarketPlaceEnum::AMAZON);

        $message = new FetchMarketPlaceOrdersMessage(MarketPlaceEnum::AMAZON);
        ($this->messageHandler)($message);

        $this->assertTrue(true);
    }
    #[Test]
    public function multipleMarketplacesSync(): void
    {
        $allegroResponses = [
            new MockResponse(json_encode([
                'status' => 'SUCCESS',
                'sources' => ['allegro' => ['12345' => 'Allegro PL']]
            ])),
            new MockResponse(json_encode([
                'status' => 'SUCCESS',
                'orders' => [['order_id' => 1]]
            ])),
            new MockResponse(json_encode([
                'status' => 'SUCCESS',
                'statuses' => [['id' => 1, 'name' => 'New']]
            ])),
        ];

        $amazonResponses = [
            new MockResponse(json_encode([
                'status' => 'SUCCESS',
                'sources' => ['amazon' => ['67890' => 'Amazon EU']]
            ])),
            new MockResponse(json_encode([
                'status' => 'SUCCESS',
                'orders' => [['order_id' => 2]]
            ])),
            new MockResponse(json_encode([
                'status' => 'SUCCESS',
                'statuses' => [['id' => 1, 'name' => 'New']]
            ])),
        ];


        $this->httpClient->setResponseFactory($allegroResponses);
        $this->orderSyncService->validateAndDispatchSync(MarketPlaceEnum::ALLEGRO);
        $allegroMessage = new FetchMarketPlaceOrdersMessage(MarketPlaceEnum::ALLEGRO);
        ($this->messageHandler)($allegroMessage);


        $this->setUp();
        $this->httpClient->setResponseFactory($amazonResponses);
        $this->orderSyncService->validateAndDispatchSync(MarketPlaceEnum::AMAZON);
        $amazonMessage = new FetchMarketPlaceOrdersMessage(MarketPlaceEnum::AMAZON);
        ($this->messageHandler)($amazonMessage);

        $this->assertTrue(true);
    }
}
