<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Tests;

use Bannerstop\MentionApi\Exception\UnauthorizedException;
use Bannerstop\MentionApi\Exception\UnexpectedResponseException;
use Bannerstop\MentionApi\Tests\Fixtures\Answers;

final class OpenApiTest extends ClientTestCase
{
    public function testOpenApiFetchesTheDocumentOfYourConsumer(): void
    {
        $document = ['openapi' => '3.1.0', 'info' => ['title' => 'mention-api /v2', 'version' => '4'], 'paths' => []];
        $this->http->enqueue(Answers::json($document, 200, ['X-Request-Id' => 'req_doc']));

        $result = $this->client()->openApi();

        $request = $this->http->lastRequest();
        self::assertSame('GET', $request->getMethod());
        self::assertSame('https://mention-api.example.com/v2/openapi.json', (string) $request->getUri());
        self::assertSame('Bearer mapi_ck_example', $request->getHeaderLine('Authorization'));
        self::assertSame($document, $result);
    }

    public function testOpenApiIsRetriedLikeAQueryCall(): void
    {
        $this->http
            ->enqueue(Answers::problem(503, 'mention-unavailable'))
            ->enqueue(Answers::json(['openapi' => '3.1.0', 'paths' => []]));

        $this->client()->openApi();

        self::assertCount(2, $this->http->requests);
    }

    public function testOpenApiThrowsTheTypedExceptionOfAProblem(): void
    {
        $this->http->enqueue(Answers::problem(401, 'unauthorized'));

        $this->expectException(UnauthorizedException::class);

        $this->client()->openApi();
    }

    public function testAnswerThatIsNoOpenApiDocumentIsRefused(): void
    {
        $this->http->enqueue(Answers::json(['data' => []], 200, ['X-Request-Id' => 'req_x']));

        $this->expectException(UnexpectedResponseException::class);

        $this->client()->openApi();
    }
}
