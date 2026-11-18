<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Tests;

use Bannerstop\MentionApi\Exception\OutsideScopeException;
use Bannerstop\MentionApi\Exception\UnknownQueryException;
use Bannerstop\MentionApi\Tests\Fixtures\Answers;

final class QueryCatalogTest extends ClientTestCase
{
    public function testListQueriesReturnsTheQueriesOfYourScope(): void
    {
        $this->http->enqueue(Answers::json(
            ['data' => [
                ['name' => 'getThings', 'version' => 2, 'description' => 'All things'],
                ['name' => 'getOtherThings', 'version' => 1, 'description' => ''],
            ]],
            200,
            ['X-Request-Id' => 'req_list'],
        ));

        $list = $this->client()->listQueries();

        $request = $this->http->lastRequest();
        self::assertSame('GET', $request->getMethod());
        self::assertSame('https://mention-api.example.com/v2/queries', (string) $request->getUri());
        self::assertSame('Bearer mapi_ck_example', $request->getHeaderLine('Authorization'));
        self::assertSame('req_list', $list->getRequestId());
        self::assertCount(2, $list);
        self::assertSame(
            [
                ['name' => 'getThings', 'version' => 2, 'description' => 'All things'],
                ['name' => 'getOtherThings', 'version' => 1, 'description' => ''],
            ],
            $list->getQueries(),
        );
        self::assertSame(['getThings', 'getOtherThings'], $list->getNames());
    }

    public function testDescribeQueryTellsWhatToSendAndWhatComesBack(): void
    {
        $this->http->enqueue(Answers::json(
            [
                'name' => 'getThings',
                'version' => 2,
                'description' => 'All things',
                'parameters' => [['name' => 'limit', 'type' => 'int'], ['name' => 'since', 'type' => 'date']],
                'columns' => [['name' => 'id', 'type' => 'int', 'description' => 'The id']],
                'databases' => ['bannerstop_gmbh'],
                'timeout_seconds' => 20,
                'row_limit' => 5000,
            ],
            200,
            ['X-Request-Id' => 'req_describe'],
        ));

        $query = $this->client()->describeQuery('getThings');

        self::assertSame('https://mention-api.example.com/v2/queries/getThings', (string) $this->http->lastRequest()->getUri());
        self::assertSame('GET', $this->http->lastRequest()->getMethod());
        self::assertSame('req_describe', $query->getRequestId());
        self::assertSame('getThings', $query->getName());
        self::assertSame(2, $query->getVersion());
        self::assertSame('All things', $query->getDescription());
        self::assertSame([['name' => 'limit', 'type' => 'int'], ['name' => 'since', 'type' => 'date']], $query->getParameters());
        self::assertSame([['name' => 'id', 'type' => 'int', 'description' => 'The id']], $query->getColumns());
        self::assertSame(['bannerstop_gmbh'], $query->getDatabases());
        self::assertSame(20, $query->getTimeoutSeconds());
        self::assertSame(5000, $query->getRowLimit());
        self::assertFalse($query->isDeprecated());
        self::assertNull($query->getDeprecatedSince());
    }

    public function testADeprecatedQueryIsMarkedOnDescribeAndRun(): void
    {
        $describe = Answers::json(['name' => 'getOld', 'version' => 1, 'description' => '', 'parameters' => [], 'columns' => [], 'databases' => [], 'timeout_seconds' => 5, 'row_limit' => 10], 200, ['Deprecation' => '@1763467200']);
        $run = Answers::rows([], 'getOld', 'req_old', ['Deprecation' => '@1763467200']);
        $this->http->enqueue($describe)->enqueue($run);

        $client = $this->client();
        $described = $client->describeQuery('getOld');
        $result = $client->run('getOld');

        foreach ([$described, $result] as $answer) {
            self::assertTrue($answer->isDeprecated());
            self::assertSame('2025-11-18T12:00:00+00:00', $answer->getDeprecatedSince()->format('c'));
        }
    }

    public function testListingAndDescribingAreRetriedLikeRunning(): void
    {
        $this->http
            ->enqueue(Answers::problem(503, 'no-compiled-catalog', 'No Catalog available', 'Not yet.', 'req_1', [], ['Retry-After' => '1']))
            ->enqueue(Answers::json(['data' => []]))
            ->enqueue(Answers::problem(429, 'too-many-requests', 'Too many concurrent calls', 'Slow down.', 'req_2', [], ['Retry-After' => '1']))
            ->enqueue(Answers::json(['name' => 'getThings', 'version' => 1, 'description' => '', 'parameters' => [], 'columns' => [], 'databases' => [], 'timeout_seconds' => 5, 'row_limit' => 10]));

        $client = $this->client();
        $client->listQueries();
        $client->describeQuery('getThings');

        self::assertCount(4, $this->http->requests);
    }

    public function testDescribingAQueryOutsideTheScopeIsRefused(): void
    {
        $this->http->enqueue(Answers::problem(403, 'outside-scope', 'Query not in Scope', 'No.', 'req_403'));

        $this->expectException(OutsideScopeException::class);

        $this->client()->describeQuery('getSecrets');
    }

    public function testDescribingAnUnknownQueryIsNotFound(): void
    {
        $this->http->enqueue(Answers::problem(404, 'unknown-query', 'Unknown Query', 'There is no Query named "getNothing".', 'req_404'));

        $this->expectException(UnknownQueryException::class);

        $this->client()->describeQuery('getNothing');
    }

    public function testADamagedListIsAnUnexpectedResponse(): void
    {
        $this->http->enqueue(Answers::json(['data' => 'nope']));

        $this->expectException(\Bannerstop\MentionApi\Exception\UnexpectedResponseException::class);

        $this->client()->listQueries();
    }
}
