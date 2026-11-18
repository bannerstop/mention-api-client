# bannerstop/mention-api-client

A small, framework-agnostic PHP client for the `/v2` interface of mention-api.
It lists, describes and runs **Queries** (named, typed reads of Mention data) and
lists and runs **Routines** (named, reviewed writes), and takes care of the parts
every caller would otherwise write again:

- **Authentication** with an API key, or as a Keycloak client with client
  credentials (the access token is cached and renewed shortly before it expires)
- **One exception class per error code** of the problem+json answers
- **The request id** on every result and every exception
- **A timeout** (30 s by default)
- **Retries** for Queries (up to 2 times on 429, 503 and connection errors,
  honouring `Retry-After`), never for Routines

It only depends on PSR interfaces (PSR-18 HTTP client, PSR-17 factories, PSR-16
cache, PSR-20 clock), so it runs with any HTTP client and inside any framework.

Integrations on top of this package (coming soon):

- Symfony: `bannerstop/mention-api-client-bundle`
- Laravel: `bannerstop/mention-api-client-laravel`

## Versions

There is one major version for every supported PHP version, 7.4 to 8.5. A new
major version is released only when the minimum PHP version rises or a
dependency range cannot be held any longer; everything else, including new
features, ships in a minor version. The integration packages require `^1.0`.

| Version | PHP        |
|---------|------------|
| 1.x     | 7.4 - 8.5+ |

The package follows [Semantic Versioning](https://semver.org/). Classes and
methods marked `@internal` are not part of the public API.

## Installation

```bash
composer require bannerstop/mention-api-client
```

You also need a [PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP client and
[PSR-17](https://www.php-fig.org/psr/psr-17/) factories, for example:

```bash
composer require guzzlehttp/guzzle               # Guzzle 7, ships both
composer require symfony/http-client nyholm/psr7 # Symfony HttpClient (Psr18Client)
composer require php-http/guzzle6-adapter nyholm/psr7 # projects stuck on Guzzle 6
```

## Configuration

Your app calls mention-api as a *Consumer* with its own credential. The Scope of
the Consumer decides which Queries and Routines it may call. Pick one of two
ways to authenticate.

### API key

```php
use Bannerstop\MentionApi\MentionApiConfig;

$config = MentionApiConfig::forApiKey(
    'https://mention-api.example.com', // base URL, without /v2
    getenv('MENTION_API_KEY')
);
```

### Keycloak client credentials

The Keycloak client needs service accounts enabled and the client scope
`mention-api`. The client fetches the access token itself, keeps it in the PSR-16
cache you pass in (and in memory) and fetches a new one 30 seconds before the
old one expires.

```php
$config = MentionApiConfig::forClientCredentials(
    'https://mention-api.example.com',
    'https://sso.example.com/realms/example/protocol/openid-connect/token',
    'my-app',                           // client id
    getenv('MENTION_API_CLIENT_SECRET') // client secret
    // , 'mention-api'                  // scope, this is the default
);
```

Pass a cache to the client in production: without one, every PHP process fetches
its own token.

### Timeout

```php
$config = $config->withTimeout(10.0); // seconds, 30.0 by default
```

PSR-18 has no way to set a timeout, so the timeout of the configuration reaches
only HTTP clients that implement `Bannerstop\MentionApi\Http\TimeoutAware` (a thin
decorator around your client does it). **For every other client, set the
timeout on the client itself**, because most clients wait forever by default:

```php
new GuzzleHttp\Client(['timeout' => 30, 'connect_timeout' => 10]);
```

## Usage

### Create the client

```php
use Bannerstop\MentionApi\MentionApiClient;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;

$factory = new HttpFactory();
$mention = new MentionApiClient(
    $config,
    new Client(['timeout' => 30, 'connect_timeout' => 10]), // PSR-18 client
    $factory,   // PSR-17 request factory
    $factory,   // PSR-17 stream factory
    $psr16Cache // optional, used for the access token of client credentials
);
```

The optional sixth and seventh arguments are a PSR-20 clock and a
`Bannerstop\MentionApi\Http\Sleeper`; replace them in tests so that retries do not
wait.

### Run a Query

```php
$result = $mention->run('getOrderData', ['orderId' => 12345]);

foreach ($result as $row) {          // or $result->getRows()
    echo $row['orderId'], ' ', $row['total'], "\n";
}
echo count($result), ' rows, request ', $result->getRequestId();
```

Parameters are sent as JSON and must have the type the Query declares: an `int`
is a PHP int, not `"12"`, a `date` is `"2026-11-18"`, a list is an array. mention-api
refuses anything else with `InvalidParametersException`. Decimal columns arrive as
strings, so money keeps its precision.

A second Mention database can be named as the third argument:

```php
$mention->run('getOrderData', ['orderId' => 12345], 'example_db');
```

`QueryResult` also has `getQuery()`, `getVersion()` and `getDurationMs()`.

### Find out what you may call

```php
$queries = $mention->listQueries();          // the Queries in the Scope of your Consumer
$queries->getNames();                        // ['getOrderData', ...]

$query = $mention->describeQuery('getOrderData');
$query->getParameters();                     // [['name' => 'orderId', 'type' => 'int']]
$query->getColumns();                        // name, type and description of each column
$query->getDatabases();
$query->getTimeoutSeconds();
$query->getRowLimit();                       // more rows are refused, never cut off silently
```

A Query that is deprecated still works. Its results and descriptions say so, so
that you can alert before it is retired:

```php
if ($result->isDeprecated()) {
    $logger->warning('Query is deprecated', ['since' => $result->getDeprecatedSince()]);
}
```

### Run a Routine

A Routine writes to Mention.

```php
$routines = $mention->listRoutines();        // names, Parameters (with format and maximum length)

$result = $mention->runRoutine('setContactEmail', ['contactId' => 7, 'email' => 'a@example.com']);
$result->getAction();                        // "inserted", "updated" or "unchanged"
$result->getRowsWritten();
```

### Errors

Every failed call throws a subclass of `Bannerstop\MentionApi\Exception\MentionApiException`
(a `RuntimeException`), and so does a failed call that never got an answer.
`getRequestId()` is on every one of them and in the message; log it and quote it when you
ask about a call.

```php
use Bannerstop\MentionApi\Exception\InvalidParametersException;
use Bannerstop\MentionApi\Exception\MentionApiException;
use Bannerstop\MentionApi\Exception\ProblemException;
use Bannerstop\MentionApi\Exception\UnavailableException;

try {
    $mention->run('getOrderData', ['orderId' => 'abc']);
} catch (InvalidParametersException $e) {
    $e->getErrors();        // ['orderId' => 'Expected an integer.']
} catch (UnavailableException $e) {
    // Mention could not be used, even after the retries: try again later
} catch (ProblemException $e) {
    $e->getStatus();        // HTTP status
    $e->getProblemCode();   // e.g. "unknown-query"
    $e->getRequestId();     // e.g. "req_..."
} catch (MentionApiException $e) {
    // no answer at all (TransportException), a refused token request (TokenRequestException)
    // or an answer that is not from mention-api (UnexpectedResponseException)
}
```

| Status | Problem code | Exception | What to do |
| --- | --- | --- | --- |
| 401 | `unauthorized` | `UnauthorizedException` | Fix the credential; do not retry |
| 403 | `forbidden` | `ForbiddenException` | Ask for it to be added to your Scope |
| 403 | `outside-scope`, `not-in-scope`, `not-owner` | `OutsideScopeException`, `NotInScopeException`, `NotOwnerException` (all `ForbiddenException`) | Same |
| 404 | `not-found`, `unknown-query`, `unknown-routine`, `item-not-found` | `NotFoundException`, `UnknownQueryException`, `UnknownRoutineException`, `ItemNotFoundException` (the last three are `NotFoundException`) | Fix the name or the item |
| 409 | `ambiguous-item` | `AmbiguousItemException` | Report it with the request id |
| 422 | `invalid-request`, `invalid-parameters`, `database-not-allowed`, `result-too-large` | `InvalidRequestException`, `InvalidParametersException`, `DatabaseNotAllowedException`, `ResultTooLargeException` | Fix the request; do not retry |
| 429 | `too-many-requests` | `TooManyRequestsException` | Retried for Queries; `getRetryAfter()` |
| 500 | `failed`, `internal-error` | `ExecutionFailedException`, `InternalErrorException` | Report it with the request id |
| 503 | `circuit-open`, `mention-unavailable`, `no-compiled-catalog` | `CircuitOpenException`, `MentionUnavailableException`, `NoCompiledCatalogException` (all `UnavailableException`) | Retried for Queries; try again later |
| 504 | `timeout` | `TimeoutException` | Narrow the Parameters |
| any | `http-error` or a code this version does not know | `HttpErrorException`, `UnknownProblemException` | Report it with the request id |

Calls with a name that cannot be valid (it has to start with a letter and have
letters and digits only) or with a list instead of named Parameters throw a plain
`InvalidArgumentException` before anything is sent.

### Retries

| Call | Retried | How |
| --- | --- | --- |
| `run`, `listQueries`, `describeQuery` | up to 2 times on 429, 503 and connection errors | waits the `Retry-After` of the answer, at most 10 s; 1 s, then 2 s without one |
| `runRoutine`, `listRoutines` | never | a Routine writes to Mention, so a call that failed without an answer may have run: check before you run it again |

Everything else (401, 403, 404, 422, 500, 504) is final at once. When the retries are used up, the last
error is thrown.

## Wiring it by hand

### Plain PHP

The example under "Create the client" is all there is to it. Keep the client in
your container or a static factory and reuse it, so that the access token is
fetched once.

### Magento 2

The package needs no framework. A small factory class reads the settings and builds
the client; Magento's dependency injection creates the factory, and your code asks
the factory for the client.

```php
<?php
declare(strict_types=1);

namespace Vendor\Module\Model;

use Bannerstop\MentionApi\MentionApiClient;
use Bannerstop\MentionApi\MentionApiConfig;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;

class MentionApiClientFactory
{
    /** @var ScopeConfigInterface */
    private $scopeConfig;
    /** @var EncryptorInterface */
    private $encryptor;
    /** @var ClientInterface */
    private $http; // a PSR-18 client, see di.xml
    /** @var MentionApiClient|null */
    private $client;

    public function __construct(ScopeConfigInterface $scopeConfig, EncryptorInterface $encryptor, ClientInterface $http)
    {
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
        $this->http = $http;
    }

    public function create(): MentionApiClient
    {
        if (null === $this->client) {
            $config = MentionApiConfig::forApiKey(
                (string) $this->scopeConfig->getValue('vendor_module/mention_api/base_url'),
                $this->encryptor->decrypt((string) $this->scopeConfig->getValue('vendor_module/mention_api/api_key'))
            );
            $factory = new Psr17Factory();
            $this->client = new MentionApiClient($config, $this->http, $factory, $factory);
        }

        return $this->client;
    }
}
```

```xml
<!-- etc/di.xml, a sketch: any PSR-18 client will do; this one is Guzzle 6 through php-http/guzzle6-adapter -->
<type name="Vendor\Module\Model\MentionApiClientFactory">
    <arguments>
        <argument name="http" xsi:type="object">Vendor\Module\Model\MentionHttpClient</argument>
    </arguments>
</type>
<virtualType name="Vendor\Module\Model\MentionHttpClient" type="Http\Adapter\Guzzle6\Client">
    <arguments>
        <argument name="client" xsi:type="object">Vendor\Module\Model\MentionGuzzle</argument>
    </arguments>
</virtualType>
<virtualType name="Vendor\Module\Model\MentionGuzzle" type="GuzzleHttp\Client">
    <arguments>
        <argument name="config" xsi:type="array">
            <item name="timeout" xsi:type="number">30</item>
            <item name="connect_timeout" xsi:type="number">10</item>
        </argument>
    </arguments>
</virtualType>
```

The example needs `nyholm/psr7` (PSR-17 factories) and a PSR-18 client such as `php-http/guzzle6-adapter`.

Use an API key in a web request: a web request is its own PHP process, and client
credentials without a cache would fetch a token every time. For client credentials,
pass a PSR-16 cache as the fifth argument.

## Development

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
```

The tests run against a scripted PSR-18 client; no network is needed. CI runs them
on every PHP version from 7.4 to 8.5, with the highest and the lowest allowed
dependencies.

## Security

See [SECURITY.md](SECURITY.md).

## License

MIT, see [LICENSE](LICENSE).
