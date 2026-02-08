#  BaseLinker Integration Module

Moduł integracyjny dla systemów helpdesk umożliwiający pobieranie zamówień z różnych marketplace'ów poprzez API BaseLinker.

---

##  Wymagania

- PHP 8.4
- Symfony 7.4+
- Composer
- Konto BaseLinker z wygenerowanym API Key

---

## Instalacja

### 1. Klonowanie repozytorium
```bash
git clone <repository-url>
cd baselinker-integration
```

### 2. Instalacja zależności
```bash
composer install
```

### 3. Konfiguracja środowiska
```bash
cp .env .env.local
```

---

## Konfiguracja

### 1. Ustaw API Key i URL BaseLinker

Edytuj plik `.env.local`:
```env
BASELINKER_API_KEY=YOUR_API_KEY
BASELINKER_API_URL=https://api.baselinker.com/connector.php
BASELINKER_ALLEGRO_ID=YOUR_ALLEGRO_ID
BASELINKER_AMAZON_ID=YOUR_AMAZON_ID
BASELINKER_PERSONAL_ID=0
```

### 2. Znajdowanie source_id dla marketplace'ów

Aby znaleźć właściwy `source_id` dla Twojego marketplace:

1. Zaloguj się do panelu BaseLinker
2. Przejdź do: **INTEGRACJE**
3. **Ustawienia integracji**
3. Znajdź swój marketplace i skopiuj jego ID (widoczne w URL lub w szczegółach)
4. Możesz uruchomić komende ``` bash php bin/console app:baselinker-integration allegro``` wtedy w dev.log będą widoczne dostępne sources z base linker
```json
{"status":"SUCCESS","sources":{"personal":["Osobiście/tel."],"allegro":{"1":"Client"},"order_return":["Zwrot do zamówienia"]}} []
```


##  Użycie

### Pobieranie zamówień przez CLI
```bash
# Synchronizacja zamówień z Allegro
php bin/console app:baselinker-integration allegro

# Synchronizacja zamówień z Amazon
php bin/console app:baselinker-integration amazon

# Synchronizacja zamówień ze sklepu własnego
php bin/console app:baselinker-integration personal
```

### Dostępne marketplace'y

- `ALLEGRO` - Zamówienia z Allegro
- `AMAZON` - Zamówienia z Amazon
- `PERSONAL` - Zamówienia ze sklepu własnego


##  Architektura

### Struktura projektu
```
src/
├── Client/
│   ├── BaseLinkerClient.php              # HTTP komunikacja z API BaseLinker
│   └── BaseLinkerClientInterface.php
├── Command/
│   └── FetchOrderByMarketPlaceCommand.php # CLI command
├── Enum/
│   ├── BaseLinkerMethodEnum.php          # Metody API BaseLinker
│   └── MarketPlaceEnum.php               # Obsługiwane marketplace'y
├── Exception/
│   └── MarketPlaceNotConfiguredException.php
├── Message/
│   └── FetchMarketPlaceOrdersMessage.php # Message dla Messenger
├── MessageHandler/
│   └── FetchMarketPlaceOrdersMessageHandler.php # Handler message
├── Performance/
│   └── PerformanceLogger.php             # Monitoring wydajności
├── Request/
│   ├── BaseLinkerRequest.php             
│   ├── BaseLinkerRequestInterface.php
│   └── BaseLinkerRequestFactory.php      # Factory Pattern
├── Services/
│   ├── OrderSyncService.php              # Główny serwis synchronizacji
│   └── OrderFetchService.php             # Serwis pobierania danych z API
├── Validator/
│   └── MarketplaceConfigurationValidator.php # Walidacja konfiguracji
└── MarketplaceSourceProvider.php         # Provider dla source_id
```

### Zastosowane wzorce projektowe

#### 1. **Factory Pattern**
`BaseLinkerRequestFactory` - centralizacja tworzenia różnych typów zapytań do API.
```php
$request = $this->requestFactory->createGetOrdersRequest($marketplace);
$response = $this->client->request($request);
```

**Zalety:**
- Enkapsulacja logiki tworzenia requestów
- Łatwe dodawanie nowych typów zapytań
- Testowanie poprzez mockowanie factory

#### 2. **Strategy Pattern / Command Pattern**
`BaseLinkerRequestInterface` - różne typy requestów implementujące wspólny interfejs.
```php
interface BaseLinkerRequestInterface
{
    public function getMethod(): string;
    public function getParameters(): array;
}
```

**Zalety:**
- Każdy request to osobny obiekt
- Możliwość rozbudowy o walidację
- Type-safe parametry

#### 3. **Provider Pattern**
`MarketplaceSourceProvider` - dostarczanie konfiguracji marketplace'ów z DI.
```php
$sourceId = $this->marketplaceProvider->getSourceId(MarketPlaceEnum::ALLEGRO);
```

**Zalety:**
- Centralizacja konfiguracji
- Łatwe testowanie
- Możliwość zmiany źródła konfiguracji (DB, Redis)

#### 4. **Message/Handler Pattern** (Symfony Messenger)
synchroniczne kolejkowanie zadań.
```php
// Message
class FetchMarketPlaceOrdersMessage
{
    public function __construct(
        private readonly MarketPlaceEnum $marketplace
    ) {}
}

// Handler
#[AsMessageHandler]
class FetchMarketPlaceOrdersMessageHandler
{
    public function __invoke(FetchMarketPlaceOrdersMessage $message): void
    {
        // Logika pobierania
    }
}
```

**Zalety:**
- Oddzielenie dispatchowania od wykonania
- Retry mechanism
- Możliwość przejścia na async (RabbitMQ, Redis)

#### 5. **Validator Pattern**
`MarketplaceConfigurationValidator` - dedykowana klasa do walidacji.
```php
class MarketplaceConfigurationValidator
{
    public function validate(MarketPlaceEnum $marketplace): void
    {
        $this->validateSymfonyConfiguration($marketplace);
        $this->validateBaseLinkerConfiguration($marketplace);
    }
}
```

**Zalety:**
- Single Responsibility Principle
- Testowanie w izolacji
- Możliwość rozbudowy o nowe walidacje

#### 6. **Service Layer Pattern**
Oddzielenie logiki biznesowej (`OrderSyncService`, `OrderFetchService`) od infrastruktury.

**Zalety:**
- Reużywalność logiki
- Łatwe testowanie

#### 7. **Dependency Injection**
Wszystkie zależności wstrzykiwane przez konstruktor.
```php
public function __construct(
    private readonly LoggerInterface $logger,
    private readonly OrderFetchService $orderFetchService,
    private readonly PerformanceLogger $performanceLogger,
) {}
```

**Zalety:**
- Testowanie przez mockowanie
- Loose coupling
- Symfony autowiring

### Przepływ danych
```
[CLI Command]
    ↓
[OrderSyncService]
    ├─→ [MarketplaceConfigurationValidator]
    │    ├─→ Walidacja Symfony config (MarketplaceSourceProvider)
    │    └─→ Walidacja BaseLinker API (BaseLinkerClient)
    └─→ Dispatch Message (MessageBus)
         ↓
[FetchMarketPlaceOrdersMessageHandler]
    ├─→ [PerformanceLogger] - start measure
    ├─→ [OrderFetchService]
    │    ├─→ fetchOrders() → [BaseLinkerClient]
    │    └─→ fetchOrderStatuses() → [BaseLinkerClient]
    ├─→ [PerformanceLogger] - end measure
    └─→ processOrders() -> aktualnie zwraca log z danymi, docelowo do helpdesk
```



##  Testy

### Struktura testów
```
tests/
├── Unit/
│   ├── Client/
│   │   └── BaseLinkerClientTest.php
│   ├── MessageHandler/
│   │   └── FetchMarketPlaceOrdersMessageHandlerTest.php
│   ├── Performance/
│   │   └── PerformanceLoggerTest.php
│   ├── Request/
│   │   ├── BaseLinkerRequestFactoryTest.php
│   │   └── BaseLinkerRequestTest.php
│   ├── Services/
│   │   ├── OrderSyncServiceTest.php
│   │   └── OrderFetchServiceTest.php
│   └── Validator/
│       └── MarketplaceConfigurationValidatorTest.php
└── Integration/
    └── OrderSyncIntegrationTest.php
```

### Uruchomienie testów

#### Wszystkie testy:
```bash
php bin/phpunit --testdox
```

#### Tylko testy jednostkowe:
```bash
php bin/phpunit tests/Unit
```

#### Tylko testy integracyjne:
```bash
php bin/phpunit tests/Integration
```

#### Konkretna klasa:
```bash
php bin/phpunit tests/Unit/Services/OrderSyncServiceTest.php
```



##  Monitoring i logowanie

### Lokalizacja logów
```
var/log/
├── performance.log    # Metryki wydajności (JSON)
├── baselinker.log     # Operacje API (JSON)
└── dev.log           # Ogólny log deweloperski
```

### Kanały logowania

Moduł wykorzystuje dedykowany kanał `baselinker`:
```yaml
# config/packages/monolog.yaml
    handlers:
        baselinker:
            type: stream
            path: '%kernel.logs_dir%/baselinker.log'
            channels: [ "baselinker", "!performance", "!event"]
            formatter: monolog.formatter.json
```

### Format logów

Wszystkie logi w formacie **JSON** dla łatwego parsowania:

### Monitorowane metryki

#### 1. Performance Metrics
```json
{
    "message": "Performance metric",
    "context": {
        "operation": "fetch_marketplace_data",
        "duration_ms": 496.21,
        "memory_mb": 0,
        "success": true
    },
    "level": 200,
    "level_name": "INFO",
    "channel": "performance",
    "datetime": "2026-02-08T20:19:34.782205+00:00",
    "extra": {}
}
```


#### 2. API Call Logs
```json
{
  "message": "BaseLinker API call",
  "context": {
    "method": "getOrders",
    "parameters": {
      "order_source_id": 12345,
      "date_confirmed_from": 1707388800
    }
  }
}
```

#### 3. Error Logs
```json
{
  "message": "BaseLinker API returned ERROR",
  "context": {
    "status": "ERROR",
    "error_code": "ERROR_INVALID_TOKEN",
    "error_message": "Invalid API token",
    "method": "getOrders"
  },
  "level_name": "ERROR"
}
```

#### 4. Validation Logs
```json
{
  "message": "Marketplace configuration validation failed",
  "context": {
    "marketplace": "ALLEGRO",
    "reason": "Marketplace ALLEGRO is not configured in Symfony services."
  },
  "level_name": "WARNING"
}
```


### PerformanceLogger - użycie
```php
// Metoda 1: Start/End
$this->performanceLogger->startMeasure('my_operation');
// ... kod ...
$this->performanceLogger->endMeasure('my_operation');

// Metoda 2: Measure (z callback)
$result = $this->performanceLogger->measure('my_operation', function() {
    return $this->heavyComputation();
});

```


## Rozszerzanie funkcjonalności

### Dodawanie nowego marketplace
#### 1. Dodaj do .env.local
```php
BASELINKER_EBAY_ID= 'ID'
```
#### 2. Dodaj do enum
```php
// src/Enum/MarketPlaceEnum.php
enum MarketPlaceEnum : string {
    case ALLEGRO = 'ALLEGRO';
    case AMAZON = 'AMAZON';
    case PERSONAL = 'PERSONAL';
    case EBAY = 'EBAY';  // ← NOWY
}
```

#### 3. Skonfiguruj source_id
```yaml
# config/services.yaml
parameters:
    baselinker.marketplace_sources:
        allegro: '%env(BASELINKER_ALLEGRO_ID)%'
        amazon: '%env(BASELINKER_AMAZON_ID)%'
        personal: '%env(BASELINKER_PERSONAL_ID)%'
        ebay: '%env(BASELINKER_EBAY_ID)%'  # ← NOWY source_id z BaseLinker
```

#### 4. Użyj
```bash
php bin/console app:baselinker-integration EBAY
```

**To wszystko!** 

---

### Dodawanie nowego typu zapytania do API

#### 1. Dodaj metodę do enum
```php
// src/Enum/BaseLinkerMethodEnum.php
enum BaseLinkerMethodEnum: string {
    case GET_ORDERS = 'getOrders';
    case GET_ORDER_SOURCES = 'getOrderSources';
    case GET_ORDER_STATUS_LIST = 'getOrderStatusList';
    case GET_INVENTORIES = 'getInventories';  // ← NOWA METODA
    case GET_PRODUCTS = 'getProducts';        // ← NOWA METODA
}
```

#### 2. Dodaj metodę w Factory
```php
// src/Request/BaseLinkerRequestFactory.php
public function createGetInventoriesRequest(?int $inventoryId = null): BaseLinkerRequest
{
    $parameters = [];
    
    if ($inventoryId !== null) {
        $parameters['inventory_id'] = $inventoryId;
    }
    
    return new BaseLinkerRequest(
        method: BaseLinkerMethodEnum::GET_INVENTORIES->value,
        parameters: $parameters
    );
}

public function createGetProductsRequest(int $inventoryId): BaseLinkerRequest
{
    return new BaseLinkerRequest(
        method: BaseLinkerMethodEnum::GET_PRODUCTS->value,
        parameters: [
            'inventory_id' => $inventoryId,
        ]
    );
}
```

#### 3. Użyj w kodzie
```php
// Przykład: pobieranie produktów
$request = $this->requestFactory->createGetProductsRequest(
    inventoryId: 123,
);

$response = $this->client->request($request);
$products = $response['products'] ?? [];
```


---


## Troubleshooting

### Błąd: "Marketplace X is not configured in Symfony services"

**Przyczyna:** Brak konfiguracji `source_id` w `services.yaml`.

**Rozwiązanie:**
```yaml
# config/services.yaml
parameters:
    baselinker.marketplace_sources:
        allegro: your_source_id_here
```

---

### Błąd: "Marketplace is configured in Symfony, but doesn't exist in BaseLinker"

**Przyczyna:** `source_id` w config nie istnieje w BaseLinker lub jest niepoprawny.

**Rozwiązanie:**

1. Sprawdź panel BaseLinker → **Ustawienia → Zamówienia → Źródła zamówień**
2. Znajdź właściwy `source_id` dla marketplace
3. Popraw w `services.yaml`

**Przykład:**
```yaml
# Źle (nieistniejący ID)
baselinker.marketplace_sources:
    allegro: 99999

# Dobrze
baselinker.marketplace_sources:
    allegro: 12345  # Sprawdź w panelu BaseLinker
```

---

### Błąd: "Invalid API token" / "ERROR_INVALID_TOKEN"

**Przyczyna:** Nieprawidłowy `BASELINKER_API_KEY`.

**Rozwiązanie:**

1. Wygeneruj nowy token w BaseLinker:
    - Panel BaseLinker → **Ustawienia → Integracje → API**
    - Kliknij **"Wygeneruj nowy token"**
    - Skopiuj token

2. Zaktualizuj `.env.local`:
```env
BASELINKER_API_KEY=twoj_nowy_token_tutaj
```

3. Wyczyść cache:
```bash
php bin/console cache:clear
```

---

### Błąd: "No orders found" / Puste zamówienia

**Możliwe przyczyny:**

1. **Brak zamówień w wybranym okresie**
    - Domyślnie: ostatnie 24h
    - Zmień w factory: `time() - 86400` → `time() - (7 * 86400)` (7 dni)
3. **Zły source_id**
    - Sprawdź logi: `var/log/baselinker.log`
    - Zweryfikuj source_id w panelu BaseLinker
---

## 📚 Dokumentacja API BaseLinker

### Oficjalna dokumentacja
[https://api.baselinker.com/](https://api.baselinker.com/)

### Wykorzystywane endpointy

#### 1. getOrderSources
Pobiera listę źródeł zamówień.

**Request:**
```json
{
  "method": "getOrderSources",
  "parameters": {}
}
```

**Response:**
```json
{
  "status": "SUCCESS",
  "sources": {
    "allegro": {
      "12345": "Allegro PL",
      "67890": "Allegro CZ"
    },
    "amazon": {
      "11111": "Amazon DE"
    }
  }
}
```

#### 2. getOrders
Pobiera zamówienia.

**Request:**
```json
{
  "method": "getOrders",
  "parameters": {
    "order_source_id": 12345,
    "date_confirmed_from": 1707388800,
    "get_unconfirmed_orders": true
  }
}
```

**Response:**
```json
{
  "status": "SUCCESS",
  "orders": [
    {
      "order_id": 123,
      "order_source_id": 12345,
      "date_confirmed": 1707388800,
      "status_id": 1,
      "products": [...]
    }
  ]
}
```

#### 3. getOrderStatusList
Pobiera listę statusów zamówień.

**Request:**
```json
{
  "method": "getOrderStatusList",
  "parameters": {}
}
```

**Response:**
```json
{
  "status": "SUCCESS",
  "statuses": [
    {"id": 1, "name": "New"},
    {"id": 2, "name": "Confirmed"},
    {"id": 3, "name": "Shipped"}
  ]
}
```

