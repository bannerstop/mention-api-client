<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Tests;

use Bannerstop\MentionApi\Exception\ExecutionFailedException;
use Bannerstop\MentionApi\Exception\InvalidParametersException;
use Bannerstop\MentionApi\Exception\TimeoutException;
use Bannerstop\MentionApi\Exception\TooManyRequestsException;
use Bannerstop\MentionApi\Exception\TransportException;
use Bannerstop\MentionApi\Exception\UnauthorizedException;
use Bannerstop\MentionApi\Tests\Fixtures\Answers;
use Bannerstop\MentionApi\Tests\Fixtures\NetworkFailure;
use Bannerstop\MentionApi\Tests\Fixtures\RequestFailure;
use Nyholm\Psr7\Response;

final class RetryTest extends ClientTestCase
{
    public function testATooManyRequestsAnswerIsRetriedAfterTheTimeTheServerNames(): void
    {
        $this->http
            ->enqueue(Answers::problem(429, 'too-many-requests', 'Too many concurrent calls', 'Slow down.', 'req_429', [], ['Retry-After' => '3']))
            ->enqueue(Answers::rows([['id' => 1]]));

        $result = $this->client()->run('getThings');

        self::assertSame([['id' => 1]], $result->getRows());
        self::assertCount(2, $this->http->requests);
        self::assertSame([3.0], $this->sleeper->sleeps);
    }

    public function testAnUnavailableAnswerIsRetried(): void
    {
        $this->http
            ->enqueue(Answers::problem(503, 'mention-unavailable', 'Mention unavailable', 'Down.', 'req_503', [], ['Retry-After' => '2']))
            ->enqueue(Answers::rows([]));

        $this->client()->run('getThings');

        self::assertCount(2, $this->http->requests);
    }

    public function testAnUnavailableAnswerWithoutProblemDocumentIsRetriedToo(): void
    {
        $this->http
            ->enqueue(new Response(503, ['Content-Type' => 'text/html'], 'Maintenance'))
            ->enqueue(Answers::rows([]));

        $this->client()->run('getThings');

        self::assertCount(2, $this->http->requests);
    }

    public function testAConnectionErrorIsRetried(): void
    {
        $this->http
            ->enqueue(new NetworkFailure('Connection refused'))
            ->enqueue(Answers::rows([['id' => 2]]));

        self::assertSame([['id' => 2]], $this->client()->run('getThings')->getRows());
    }

    public function testItGivesUpAfterTwoRetriesWithTheLastError(): void
    {
        $this->http->enqueue(Answers::problem(429, 'too-many-requests', 'Too many concurrent calls', 'Slow down.', 'req_last', [], ['Retry-After' => '1']));

        try {
            $this->client()->run('getThings');
            self::fail('No exception');
        } catch (TooManyRequestsException $e) {
            self::assertSame('req_last', $e->getRequestId());
            self::assertSame(1, $e->getRetryAfter());
        }
        self::assertCount(3, $this->http->requests);
        self::assertCount(2, $this->sleeper->sleeps);
    }

    public function testAConnectionErrorThatKeepsHappeningEndsAsTransportException(): void
    {
        $failure = new NetworkFailure('Connection refused');
        $this->http->enqueue($failure);

        try {
            $this->client()->run('getThings');
            self::fail('No exception');
        } catch (TransportException $e) {
            self::assertSame($failure, $e->getPrevious());
            self::assertNull($e->getRequestId());
        }
        self::assertCount(3, $this->http->requests);
    }

    public function testRetryAfterIsCappedAtTenSeconds(): void
    {
        $this->http
            ->enqueue(Answers::problem(429, 'too-many-requests', 'Too many concurrent calls', 'Slow down.', 'req_1', [], ['Retry-After' => '120']))
            ->enqueue(Answers::rows([]));

        $this->client()->run('getThings');

        self::assertSame([10.0], $this->sleeper->sleeps);
    }

    public function testRetryAfterMayBeAnHttpDate(): void
    {
        $this->http
            ->enqueue(Answers::problem(503, 'circuit-open', 'Mention unavailable', 'Paused.', 'req_1', [], ['Retry-After' => 'Wed, 18 Nov 2026 12:00:04 GMT']))
            ->enqueue(Answers::rows([]));

        $this->client()->run('getThings');

        self::assertSame([4.0], $this->sleeper->sleeps);
    }

    public function testWithoutRetryAfterTheWaitGrowsFromOneSecond(): void
    {
        $this->http
            ->enqueue(new NetworkFailure('Connection reset'))
            ->enqueue(new NetworkFailure('Connection reset'))
            ->enqueue(Answers::rows([]));

        $this->client()->run('getThings');

        self::assertSame([1.0, 2.0], $this->sleeper->sleeps);
    }

    public function testOtherFailuresAreNotRetried(): void
    {
        $cases = [
            [Answers::problem(500, 'failed', 'Query failed', 'x'), ExecutionFailedException::class],
            [Answers::problem(504, 'timeout', 'Query timed out', 'x'), TimeoutException::class],
            [Answers::problem(422, 'invalid-parameters', 'Invalid Parameters', 'x'), InvalidParametersException::class],
            [Answers::problem(401, 'unauthorized', 'Unauthorized', 'x'), UnauthorizedException::class],
        ];
        foreach ($cases as [$answer, $class]) {
            $http = new Fixtures\FakeHttpClient();
            $http->enqueue($answer);

            try {
                $this->client(null, $http)->run('getThings');
                self::fail('No exception');
            } catch (\Throwable $e) {
                self::assertInstanceOf($class, $e);
            }
            self::assertCount(1, $http->requests, $class);
        }
        self::assertSame([], $this->sleeper->sleeps);
    }

    public function testARequestTheHttpClientRefusesIsNotRetried(): void
    {
        $this->http->enqueue(new RequestFailure('Invalid request'));

        try {
            $this->client()->run('getThings');
            self::fail('No exception');
        } catch (TransportException $e) {
            self::assertInstanceOf(RequestFailure::class, $e->getPrevious());
        }
        self::assertCount(1, $this->http->requests);
    }
}
