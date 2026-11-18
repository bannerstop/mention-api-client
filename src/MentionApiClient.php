<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi;

use Bannerstop\MentionApi\Auth\ApiKeyAuthenticator;
use Bannerstop\MentionApi\Http\NativeSleeper;
use Bannerstop\MentionApi\Http\Sleeper;
use Bannerstop\MentionApi\Http\Transport;
use Bannerstop\MentionApi\Result\QueryResult;
use Bannerstop\MentionApi\Support\SystemClock;
use Psr\Clock\ClockInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Client for the /v2 interface of mention-api.
 */
final class MentionApiClient
{
    private const NAME_PATTERN = '/^[A-Za-z][A-Za-z0-9]{0,127}$/';

    private Transport $transport;

    public function __construct(
        MentionApiConfig $config,
        ClientInterface $http,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        ?CacheInterface $cache = null,
        ?ClockInterface $clock = null,
        ?Sleeper $sleeper = null
    ) {
        $this->transport = new Transport($config, $http, $requestFactory, $streamFactory, new ApiKeyAuthenticator($config->getApiKey()), $clock ?? new SystemClock(), $sleeper ?? new NativeSleeper());
    }

    /**
     * Runs a Query.
     *
     * @param array<string, mixed> $parameters the Query's Parameters by name
     * @param string|null          $database   the Mention database, the server's default when left out
     *
     * @throws \InvalidArgumentException when the name or the Parameters cannot be valid
     */
    public function run(string $name, array $parameters = [], ?string $database = null): QueryResult
    {
        $this->assertName($name);
        $body = ['parameters' => $this->parametersObject($parameters)];
        if (null !== $database) {
            $body['database'] = $database;
        }

        $response = $this->transport->send('POST', '/v2/queries/' . $name, $body, true);
        $meta = $response->getMeta();

        return new QueryResult($response->getData(), $meta['query'] ?? $name, (int) ($meta['version'] ?? 0), (int) ($meta['duration_ms'] ?? 0), $response->getRequestId());
    }

    private function assertName(string $name): void
    {
        if (1 !== preg_match(self::NAME_PATTERN, $name)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid Query or Routine name: it starts with a letter and has letters and digits only, up to 128 characters.', $name));
        }
    }

    /**
     * @param array<array-key, mixed> $parameters
     *
     * @return array<string, mixed>|\stdClass
     */
    private function parametersObject(array $parameters)
    {
        if ([] === $parameters) {
            return new \stdClass();
        }
        if (array_keys($parameters) === range(0, \count($parameters) - 1)) {
            throw new \InvalidArgumentException('Parameters must be an array keyed by Parameter name, not a list.');
        }

        return $parameters;
    }
}
