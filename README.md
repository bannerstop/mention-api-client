# bannerstop/mention-api-client

A small, framework-agnostic PHP client for the `/v2` interface of mention-api.
It lists, describes and runs **Queries** (named, typed reads of Mention data) and
lists and runs **Routines** (named, reviewed writes), and takes care of the parts
every caller would otherwise write again:

- **Authentication** with an API key, or with OAuth 2.0 client credentials
  (the access token is cached, renewed shortly before it expires and
  replaced once when the server rejects it)
- **One exception class per error code** of the problem+json answers
- **The request id** on every result and every exception
- **A timeout** (30 s by default), applied to Guzzle and Symfony HttpClient for you
- **Retries** for Queries (up to 2 times on 429, 503 and connection errors,
  honouring `Retry-After`), never for Routines
- **Typed wrappers**: a generator that turns the OpenAPI document of your
  Consumer into classes for the Parameters and rows of every Query and Routine

It only depends on PSR interfaces (PSR-18 HTTP client, PSR-17 factories, PSR-16
cache, PSR-20 clock), so it runs with any HTTP client and inside any framework.

Integrations on top of this package:

- Symfony: [`bannerstop/mention-api-client-bundle`](https://github.com/bannerstop/mention-api-client-bundle)
- Laravel: [`bannerstop/mention-api-client-laravel`](https://github.com/bannerstop/mention-api-client-laravel)

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
composer require symfony/http-client nyholm/psr7 # Symfony HttpClient (use SymfonyTimeoutClient, see "Timeout")
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

### OAuth 2.0 client credentials

Any OAuth 2.0 server that issues tokens mention-api accepts will do; pass its token
endpoint (the example below is the shape Keycloak uses). The client must be allowed
the client-credentials grant and the scope `mention-api`.

The client fetches the access token itself, keeps it in the PSR-16 cache you pass
in (and in memory) and fetches a new one 30 seconds before the old one expires.

When the server answers a call with 401 although the token has not expired (it was
revoked, or the clocks differ), the client drops the cached token, fetches a new
one and sends the call again, once. This also holds for Routines: a 401 means that
nothing ran. It does not use up the retries of a Query, and a second 401 is thrown
as `UnauthorizedException`. With an API key there is nothing to refresh, so a 401
is thrown at once.

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

PSR-18 has no way to set a timeout, and most HTTP clients wait forever or for a
minute by default. How the timeout of the configuration reaches your client
depends on the client:

| HTTP client | What you do |
| --- | --- |
| Guzzle 7 (`GuzzleHttp\Client`) | Nothing. A Guzzle client that you pass to `MentionApiClient` is wrapped in `GuzzleTimeoutClient`, which sends every call with this timeout. It replaces a `timeout` that you set on the Guzzle client itself; set `withTimeout()` on the configuration instead. |
| Symfony HttpClient | Pass `SymfonyTimeoutClient` instead of a `Psr18Client`: `new SymfonyTimeoutClient(HttpClient::create())`. It sets Symfony's `timeout` and `max_duration` (the limit for the whole call). A `Psr18Client` cannot be wrapped afterwards, so it keeps the timeout it was created with. |
| Any other PSR-18 client | The timeout is not applied. Set it on the client itself, or let a thin decorator implement `Bannerstop\MentionApi\Http\TimeoutAware`; the client then receives the timeout of the configuration. |

```php
use Bannerstop\MentionApi\Http\SymfonyTimeoutClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Component\HttpClient\HttpClient;

$http = new SymfonyTimeoutClient(HttpClient::create());
$factory = new Psr17Factory();
$mention = new MentionApiClient($config, $http, $factory, $factory);
```

The timeout also applies to the request for the access token. Guzzle and
Symfony HttpClient are optional: this package does not require them.

## Usage

### Create the client

```php
use Bannerstop\MentionApi\MentionApiClient;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;

$factory = new HttpFactory();
$mention = new MentionApiClient(
    $config,
    new Client(),   // PSR-18 client, gets the timeout of $config
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
| 401 | `unauthorized` | `UnauthorizedException` | Fix the credential; a client-credentials token is replaced once before this is thrown |
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
| `runRoutine`, `listRoutines` | never (except once after a 401 with a new token, see above) | a Routine writes to Mention, so a call that failed without an answer may have run: check before you run it again |

Everything else (403, 404, 422, 500, 504) is final at once. When the retries are used up, the last
error is thrown.

A 401 with client credentials is handled apart from these retries: the cached token
is dropped, a new one is fetched and the call is sent again once, for Queries and
Routines alike, because a call that was refused for its credential has not run. It
does not count against the 2 retries of a Query. With an API key a 401 is thrown at
once.

## Typed wrappers

`run('getInvoiceTotals', [...])` returns arrays and takes any array. The
generator reads the OpenAPI document of your Consumer (`GET /v2/openapi.json`,
exactly the Queries and Routines your Consumer may call) and writes classes for
your app, so that your IDE and static analysis know the Parameters and the
columns:

```bash
vendor/bin/mention-api-client generate --namespace='App\Mention' --dir=src/Mention
```

Commit the output. Nothing is generated at runtime, and the generated code uses
the client above, so authentication, errors, retries and request ids behave the
same.

For every Query it writes a **Parameters** class, a **Row** class and a typed
method; for every Routine a Parameters class and a typed method:

```
src/Mention/
    Queries.php                          one method per Query
    Routines.php                         one method per Routine
    Query/GetInvoiceTotalsParameters.php
    Query/GetInvoiceTotalsRow.php
    Routine/SetWidgetLabelParameters.php
```

```php
use App\Mention\Queries;
use App\Mention\Query\GetInvoiceTotalsParameters;
use App\Mention\Routines;
use App\Mention\Routine\SetWidgetLabelParameters;

$queries = new Queries($mention);   // $mention is the MentionApiClient from above
$rows = $queries->getInvoiceTotals(new GetInvoiceTotalsParameters(12, '2026-01-01', '2026-01-31'));

foreach ($rows as $row) {           // GetInvoiceTotalsRow objects
    echo $row->invoiceId, ' ', $row->total, "\n";
}
echo $rows->getRequestId();         // also getVersion(), getDurationMs(), isDeprecated()

$result = (new Routines($mention))->setWidgetLabel(new SetWidgetLabelParameters(4, 'Blue', $spark));
$result->getAction();               // the RoutineResult of the generic client
```

The constructor of a Parameters class takes the required Parameters in the order
the Query declares them, then the optional ones. A Query without required Parameters can be called without
a Parameters object. Every method takes the Mention database as an optional
second argument.

A **Row** has one public property per result column, named like the column. Every
property can be `null`. Columns are typed by their database type: integers are
`int`, `bit` is `bool`, `float` and `real` are `float`. Decimal and money
columns (`decimal`, `numeric`, `money`, `smallmoney`) stay `string`, so money
keeps its precision, and so do dates and times, as the server delivers them. A column of
a type the generator does not know keeps its value as delivered. `Row::fromArray()` reads one row
of the generic result; a missing column is `null`.

### Where the document comes from

By default the document is fetched live. Pick one way to give the generator
your credentials:

- **A file that returns your configured client**, so that the generator uses the
  same wiring as your app: `--client=config/mention-client.php`, where the file
  ends with `return new MentionApiClient(...);` (see "Create the client").
- **Environment variables**: `MENTION_API_URL` and either `MENTION_API_KEY` or
  `MENTION_API_TOKEN_URL`, `MENTION_API_CLIENT_ID` and `MENTION_API_CLIENT_SECRET`
  (optionally `MENTION_API_SCOPE`). This needs Guzzle 7 or `symfony/http-client`
  in your project.
- **A file** with the document, for offline use or a saved copy:
  `--source=openapi.json`.

### PHP version of the generated code

The generator reads the PHP version of your app from `require.php` in your
`composer.json` (`--composer=<file>` for another one). When even the lowest PHP
your constraint allows is 8.2 or higher, the classes are `readonly`; otherwise
they are plain classes with typed properties that run on PHP 7.4 and later.
Both give you the same properties and methods; only the `readonly` ones are enforced by PHP itself. `--php=7.4` or `--php=8.2`
overrides the choice. The generator itself runs on PHP 7.4 to 8.5.

### Keep the classes current

The output is deterministic: the same document gives the same files, with no
timestamps and in a stable order. `--check` writes nothing, lists the files that
differ and exits with status 1, so that your CI notices when the catalog of your
Consumer changed:

```bash
vendor/bin/mention-api-client generate --check --namespace='App\Mention' --dir=src/Mention --client=config/mention-client.php
```

Run `generate` again and commit the diff when it fails. `generate` also removes
generated files of Queries and Routines that are gone; it only ever touches
files below `Query/` and `Routine/` that carry its marker comment, and
`Queries.php` and `Routines.php`. Do not edit generated files.

The generator refuses a document it cannot turn into valid classes, for example a
column whose name is not a PHP identifier, and says which Query it is about.

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

The tests run against a scripted PSR-18 client; no network is needed. The
generator is tested against a sample OpenAPI document (`tests/Fixtures/openapi.json`)
with snapshots of the generated code per PHP target in `tests/Snapshots`; after
changing the generator, regenerate them with `UPDATE_SNAPSHOTS=1 vendor/bin/phpunit`
and review the diff. CI runs them
on every PHP version from 7.4 to 8.5, with the highest and the lowest allowed
dependencies.

## Security

See [SECURITY.md](SECURITY.md).

## License

MIT, see [LICENSE](LICENSE).
