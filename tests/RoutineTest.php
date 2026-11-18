<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Tests;

use Bannerstop\MentionApi\Exception\AmbiguousItemException;
use Bannerstop\MentionApi\Exception\ItemNotFoundException;
use Bannerstop\MentionApi\Exception\MentionUnavailableException;
use Bannerstop\MentionApi\Exception\NotInScopeException;
use Bannerstop\MentionApi\Exception\TooManyRequestsException;
use Bannerstop\MentionApi\Exception\TransportException;
use Bannerstop\MentionApi\Tests\Fixtures\Answers;
use Bannerstop\MentionApi\Tests\Fixtures\NetworkFailure;

final class RoutineTest extends ClientTestCase
{
    private static function ran(string $action = 'updated', string $requestId = 'req_routine'): \Psr\Http\Message\ResponseInterface
    {
        return Answers::json(
            ['data' => ['action' => $action], 'meta' => ['routine' => 'setContactEmail', 'rows' => 'unchanged' === $action ? 0 : 1, 'duration_ms' => 34, 'request_id' => $requestId]],
            200,
            ['X-Request-Id' => $requestId],
        );
    }

    public function testListRoutinesReturnsTheRoutinesOfYourScope(): void
    {
        $this->http->enqueue(Answers::json(
            ['data' => [[
                'name' => 'setContactEmail',
                'version' => 1,
                'description' => 'Sets the e-mail address of a contact',
                'parameters' => [['name' => 'contactId', 'type' => 'int'], ['name' => 'email', 'type' => 'string', 'format' => 'email', 'max_length' => 254]],
            ]]],
            200,
            ['X-Request-Id' => 'req_routines'],
        ));

        $list = $this->client()->listRoutines();

        self::assertSame('GET', $this->http->lastRequest()->getMethod());
        self::assertSame('https://mention-api.example.com/v2/routines', (string) $this->http->lastRequest()->getUri());
        self::assertSame('req_routines', $list->getRequestId());
        self::assertCount(1, $list);
        self::assertSame(['setContactEmail'], $list->getNames());
        self::assertSame('Sets the e-mail address of a contact', $list->getRoutines()[0]['description']);
        self::assertSame(254, $list->getRoutines()[0]['parameters'][1]['max_length']);
    }

    public function testRunRoutinePostsParametersAndReportsWhatHappened(): void
    {
        $this->http->enqueue(self::ran('updated', 'req_run'));

        $result = $this->client()->runRoutine('setContactEmail', ['contactId' => 7, 'email' => 'a@example.com'], 'example_db');

        $request = $this->http->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://mention-api.example.com/v2/routines/setContactEmail', (string) $request->getUri());
        self::assertSame(['parameters' => ['contactId' => 7, 'email' => 'a@example.com'], 'database' => 'example_db'], $this->http->lastJsonBody());
        self::assertSame('updated', $result->getAction());
        self::assertSame('setContactEmail', $result->getRoutine());
        self::assertSame(1, $result->getRowsWritten());
        self::assertSame(34, $result->getDurationMs());
        self::assertSame('req_run', $result->getRequestId());
    }

    public function testAnUnchangedItemWritesNothing(): void
    {
        $this->http->enqueue(self::ran('unchanged'));

        $result = $this->client()->runRoutine('setContactEmail', ['contactId' => 7, 'email' => 'a@example.com']);

        self::assertSame('unchanged', $result->getAction());
        self::assertSame(0, $result->getRowsWritten());
    }

    public function testARoutineIsNeverRetried(): void
    {
        $failures = [
            Answers::problem(503, 'mention-unavailable', 'Mention unavailable', 'Down.', 'req_1', [], ['Retry-After' => '1']),
            Answers::problem(429, 'too-many-requests', 'Too many concurrent calls', 'Slow down.', 'req_2', [], ['Retry-After' => '1']),
            new NetworkFailure('Connection reset'),
        ];
        $expected = [MentionUnavailableException::class, TooManyRequestsException::class, TransportException::class];
        foreach ($failures as $index => $failure) {
            $http = new Fixtures\FakeHttpClient();
            $http->enqueue($failure)->enqueue(self::ran());

            try {
                $this->client(null, $http)->runRoutine('setContactEmail', ['contactId' => 7, 'email' => 'a@example.com']);
                self::fail('No exception');
            } catch (\Throwable $e) {
                self::assertInstanceOf($expected[$index], $e);
            }
            self::assertCount(1, $http->requests, $expected[$index]);
        }
        self::assertSame([], $this->sleeper->sleeps);
    }

    public function testListingRoutinesIsNotRetriedEither(): void
    {
        $this->http
            ->enqueue(Answers::problem(503, 'mention-unavailable', 'Mention unavailable', 'Down.', 'req_1'))
            ->enqueue(Answers::json(['data' => []]));

        $this->expectException(MentionUnavailableException::class);

        try {
            $this->client()->listRoutines();
        } finally {
            self::assertCount(1, $this->http->requests);
        }
    }

    public function testRoutineProblemsHaveTheirOwnExceptions(): void
    {
        $cases = [
            [Answers::problem(404, 'item-not-found', 'Item not found', 'No item with this id exists.', 'req_1'), ItemNotFoundException::class],
            [Answers::problem(409, 'ambiguous-item', 'Ambiguous item', 'Two items match.', 'req_2'), AmbiguousItemException::class],
            [Answers::problem(403, 'not-in-scope', 'Routine not in Scope', 'No.', 'req_3'), NotInScopeException::class],
        ];
        foreach ($cases as [$answer, $class]) {
            $http = new Fixtures\FakeHttpClient();
            $http->enqueue($answer);

            try {
                $this->client(null, $http)->runRoutine('setContactEmail', ['contactId' => 7, 'email' => 'a@example.com']);
                self::fail('No exception');
            } catch (\Throwable $e) {
                self::assertInstanceOf($class, $e);
            }
        }
    }

    public function testARoutineNameThatCouldChangeThePathIsRefusedWithoutACall(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->client()->runRoutine('../queries/getThings');
    }
}
