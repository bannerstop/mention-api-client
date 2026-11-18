<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Tests;

use Bannerstop\MentionApi\Tests\Fixtures\Answers;

final class RunQueryTest extends ClientTestCase
{
    public function testRunPostsParametersAsJsonAndReturnsTheRows(): void
    {
        $this->http->enqueue(Answers::rows([['id' => 7, 'total' => '12.50']], 'getOrderData'));

        $result = $this->client()->run('getOrderData', ['orderId' => 7]);

        $request = $this->http->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://mention-api.example.com/v2/queries/getOrderData', (string) $request->getUri());
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
        self::assertSame(['parameters' => ['orderId' => 7]], $this->http->lastJsonBody());
        self::assertSame([['id' => 7, 'total' => '12.50']], $result->getRows());
    }

    public function testTheApiKeyIsSentAsBearerToken(): void
    {
        $this->http->enqueue(Answers::rows([]));

        $this->client()->run('getThings');

        self::assertSame('Bearer mapi_ck_example', $this->http->lastRequest()->getHeaderLine('Authorization'));
    }

    public function testTheDatabaseIsSentWhenGiven(): void
    {
        $this->http->enqueue(Answers::rows([]));

        $this->client()->run('getThings', ['limit' => 5], 'bannerstop_gmbh');

        self::assertSame(['parameters' => ['limit' => 5], 'database' => 'bannerstop_gmbh'], $this->http->lastJsonBody());
    }

    public function testNoParametersAreSentAsAnEmptyObject(): void
    {
        $this->http->enqueue(Answers::rows([]));

        $this->client()->run('getThings');

        self::assertSame('{"parameters":{}}', (string) $this->http->lastRequest()->getBody());
    }

    public function testTheResultCarriesTheRequestIdAndTheQueryMetadata(): void
    {
        $this->http->enqueue(Answers::rows([['id' => 1], ['id' => 2]], 'getThings', 'req_abc123'));

        $result = $this->client()->run('getThings');

        self::assertSame('req_abc123', $result->getRequestId());
        self::assertSame('getThings', $result->getQuery());
        self::assertSame(3, $result->getVersion());
        self::assertSame(12, $result->getDurationMs());
        self::assertCount(2, $result);
        self::assertSame([['id' => 1], ['id' => 2]], iterator_to_array($result));
    }

    public function testTheRequestIdFallsBackToTheBodyWhenTheHeaderIsMissing(): void
    {
        $this->http->enqueue(Answers::json(['data' => [], 'meta' => ['query' => 'getThings', 'version' => 1, 'rows' => 0, 'duration_ms' => 1, 'request_id' => 'req_body']]));

        self::assertSame('req_body', $this->client()->run('getThings')->getRequestId());
    }

    public function testAQueryNameThatCouldChangeThePathIsRefusedWithoutACall(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        try {
            $this->client()->run('../routines/doIt');
        } finally {
            self::assertSame([], $this->http->requests);
        }
    }

    public function testParametersMustBeKeyedByName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->client()->run('getThings', [5, 6]);
    }
}
