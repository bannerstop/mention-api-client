<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi;

use Bannerstop\MentionApi\Auth\ApiKeyAuthenticator;
use Bannerstop\MentionApi\Http\NativeSleeper;
use Bannerstop\MentionApi\Http\Sleeper;
use Bannerstop\MentionApi\Http\Transport;
use Bannerstop\MentionApi\Exception\MentionApiException;
use Bannerstop\MentionApi\Result\QueryDescription;
use Bannerstop\MentionApi\Result\QueryList;
use Bannerstop\MentionApi\Result\QueryResult;
use Bannerstop\MentionApi\Result\RoutineList;
use Bannerstop\MentionApi\Result\RoutineResult;
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
        $response = $this->transport->send('POST', '/v2/queries/' . $name, $this->body($parameters, $database), true);
        $meta = $response->getMeta();

        return new QueryResult($response->getData(), $meta['query'] ?? $name, (int) ($meta['version'] ?? 0), (int) ($meta['duration_ms'] ?? 0), $response->getRequestId(), $response->getDeprecation());
    }

    /**
     * Lists the Queries your Consumer may call.
     *
     * @throws MentionApiException
     */
    public function listQueries(): QueryList
    {
        $response = $this->transport->send('GET', '/v2/queries', null, true);

        return new QueryList($response->getData(), $response->getRequestId());
    }

    /**
     * Describes a Query: its Parameters, result columns, databases and limits.
     *
     * @throws \InvalidArgumentException when the name cannot be valid
     * @throws MentionApiException
     */
    public function describeQuery(string $name): QueryDescription
    {
        $this->assertName($name);
        $response = $this->transport->send('GET', '/v2/queries/' . $name, null, true);
        $document = $response->getDocument();

        return new QueryDescription(
            (string) ($document['name'] ?? $name),
            (int) ($document['version'] ?? 0),
            (string) ($document['description'] ?? ''),
            $document['parameters'] ?? [],
            $document['columns'] ?? [],
            $document['databases'] ?? [],
            (int) ($document['timeout_seconds'] ?? 0),
            (int) ($document['row_limit'] ?? 0),
            $response->getRequestId(),
            $response->getDeprecation(),
        );
    }

    /**
     * Lists the Routines your Consumer may call.
     *
     * @throws MentionApiException
     */
    public function listRoutines(): RoutineList
    {
        $response = $this->transport->send('GET', '/v2/routines');

        return new RoutineList($response->getData(), $response->getRequestId());
    }

    /**
     * Runs a Routine, which writes to Mention. A Routine is never retried automatically: when this throws a
     * TransportException or a 5xx problem, the Routine may or may not have run, so check before you run it again.
     *
     * @param array<string, mixed> $parameters the Routine's Parameters by name
     * @param string|null          $database   the Mention database, the server's default when left out
     *
     * @throws \InvalidArgumentException when the name or the Parameters cannot be valid
     * @throws MentionApiException
     */
    public function runRoutine(string $name, array $parameters = [], ?string $database = null): RoutineResult
    {
        $this->assertName($name);
        $response = $this->transport->send('POST', '/v2/routines/' . $name, $this->body($parameters, $database));
        $data = $response->getData();
        $meta = $response->getMeta();

        return new RoutineResult($meta['routine'] ?? $name, (string) ($data['action'] ?? ''), (int) ($meta['rows'] ?? 0), (int) ($meta['duration_ms'] ?? 0), $response->getRequestId());
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
     * @return array<string, mixed>
     */
    private function body(array $parameters, ?string $database): array
    {
        if ([] !== $parameters && array_keys($parameters) === range(0, \count($parameters) - 1)) {
            throw new \InvalidArgumentException('Parameters must be an array keyed by Parameter name, not a list.');
        }
        $body = ['parameters' => [] === $parameters ? new \stdClass() : $parameters];
        if (null !== $database) {
            $body['database'] = $database;
        }

        return $body;
    }
}
