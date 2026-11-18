<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Tests;

use Bannerstop\MentionApi\Exception\AmbiguousItemException;
use Bannerstop\MentionApi\Exception\CircuitOpenException;
use Bannerstop\MentionApi\Exception\DatabaseNotAllowedException;
use Bannerstop\MentionApi\Exception\ExecutionFailedException;
use Bannerstop\MentionApi\Exception\ForbiddenException;
use Bannerstop\MentionApi\Exception\HttpErrorException;
use Bannerstop\MentionApi\Exception\InternalErrorException;
use Bannerstop\MentionApi\Exception\InvalidParametersException;
use Bannerstop\MentionApi\Exception\InvalidRequestException;
use Bannerstop\MentionApi\Exception\ItemNotFoundException;
use Bannerstop\MentionApi\Exception\MentionApiException;
use Bannerstop\MentionApi\Exception\MentionUnavailableException;
use Bannerstop\MentionApi\Exception\NoCompiledCatalogException;
use Bannerstop\MentionApi\Exception\NotFoundException;
use Bannerstop\MentionApi\Exception\NotInScopeException;
use Bannerstop\MentionApi\Exception\NotOwnerException;
use Bannerstop\MentionApi\Exception\OutsideScopeException;
use Bannerstop\MentionApi\Exception\ProblemException;
use Bannerstop\MentionApi\Exception\ResultTooLargeException;
use Bannerstop\MentionApi\Exception\TimeoutException;
use Bannerstop\MentionApi\Exception\TooManyRequestsException;
use Bannerstop\MentionApi\Exception\UnauthorizedException;
use Bannerstop\MentionApi\Exception\UnavailableException;
use Bannerstop\MentionApi\Exception\UnexpectedResponseException;
use Bannerstop\MentionApi\Exception\UnknownProblemException;
use Bannerstop\MentionApi\Exception\UnknownQueryException;
use Bannerstop\MentionApi\Exception\UnknownRoutineException;
use Bannerstop\MentionApi\Tests\Fixtures\Answers;
use Nyholm\Psr7\Response;

final class ProblemMappingTest extends ClientTestCase
{
    /**
     * @return array<string, array{0: int, 1: class-string<ProblemException>}>
     */
    private static function codes(): array
    {
        return [
            'invalid-request' => [422, InvalidRequestException::class],
            'invalid-parameters' => [422, InvalidParametersException::class],
            'database-not-allowed' => [422, DatabaseNotAllowedException::class],
            'result-too-large' => [422, ResultTooLargeException::class],
            'unauthorized' => [401, UnauthorizedException::class],
            'forbidden' => [403, ForbiddenException::class],
            'outside-scope' => [403, OutsideScopeException::class],
            'not-in-scope' => [403, NotInScopeException::class],
            'not-owner' => [403, NotOwnerException::class],
            'not-found' => [404, NotFoundException::class],
            'unknown-query' => [404, UnknownQueryException::class],
            'unknown-routine' => [404, UnknownRoutineException::class],
            'item-not-found' => [404, ItemNotFoundException::class],
            'ambiguous-item' => [409, AmbiguousItemException::class],
            'too-many-requests' => [429, TooManyRequestsException::class],
            'circuit-open' => [503, CircuitOpenException::class],
            'mention-unavailable' => [503, MentionUnavailableException::class],
            'no-compiled-catalog' => [503, NoCompiledCatalogException::class],
            'timeout' => [504, TimeoutException::class],
            'failed' => [500, ExecutionFailedException::class],
            'internal-error' => [500, InternalErrorException::class],
            'http-error' => [400, HttpErrorException::class],
        ];
    }

    public function testEveryProblemCodeHasItsOwnExceptionClass(): void
    {
        foreach (self::codes() as $code => [$status, $class]) {
            $http = new Fixtures\FakeHttpClient();
            $http->enqueue(Answers::problem($status, $code, 'Title of ' . $code, 'Detail of ' . $code, 'req_' . $code));

            try {
                $this->client(null, $http)->run('getThings');
                self::fail('No exception for ' . $code);
            } catch (ProblemException $e) {
                self::assertSame($class, \get_class($e), $code);
                self::assertSame($code, $e->getProblemCode());
                self::assertSame($status, $e->getStatus());
                self::assertSame('Title of ' . $code, $e->getTitle());
                self::assertSame('Detail of ' . $code, $e->getDetail());
                self::assertSame('req_' . $code, $e->getRequestId());
            }
        }
    }

    public function testExceptionsFormAHierarchyYouCanCatchBroadly(): void
    {
        self::assertTrue(is_subclass_of(UnknownQueryException::class, NotFoundException::class));
        self::assertTrue(is_subclass_of(OutsideScopeException::class, ForbiddenException::class));
        self::assertTrue(is_subclass_of(CircuitOpenException::class, UnavailableException::class));
        self::assertTrue(is_subclass_of(ProblemException::class, MentionApiException::class));
        self::assertTrue(is_subclass_of(MentionApiException::class, \RuntimeException::class));
    }

    public function testTheRequestIdIsInTheExceptionMessage(): void
    {
        $this->http->enqueue(Answers::problem(404, 'unknown-query', 'Unknown Query', 'There is no Query named "getNothing".', 'req_find_me'));

        try {
            $this->client()->run('getNothing');
            self::fail('No exception');
        } catch (UnknownQueryException $e) {
            self::assertSame('Unknown Query: There is no Query named "getNothing". (request req_find_me)', $e->getMessage());
            self::assertSame(404, $e->getCode());
        }
    }

    public function testInvalidParametersNameTheWrongOnes(): void
    {
        $this->http->enqueue(Answers::problem(422, 'invalid-parameters', 'Invalid Parameters', 'Invalid Parameters: amount.', 'req_1', ['errors' => ['amount' => 'Expected an integer.']]));

        try {
            $this->client()->run('getThings', ['amount' => '12']);
            self::fail('No exception');
        } catch (InvalidParametersException $e) {
            self::assertSame(['amount' => 'Expected an integer.'], $e->getErrors());
        }
    }

    public function testTheRequestIdFallsBackToTheProblemBody(): void
    {
        $this->http->enqueue(new Response(403, ['Content-Type' => 'application/problem+json'], json_encode([
            'type' => 'urn:bannerstop:mention-api:problem:forbidden', 'title' => 'Forbidden', 'status' => 403, 'detail' => 'No.', 'request_id' => 'req_from_body',
        ])));

        try {
            $this->client()->run('getThings');
            self::fail('No exception');
        } catch (ForbiddenException $e) {
            self::assertSame('req_from_body', $e->getRequestId());
        }
    }

    public function testAnUnknownProblemCodeIsStillAProblemException(): void
    {
        $this->http->enqueue(Answers::problem(418, 'teapot', 'Teapot', 'Short and stout.', 'req_tea'));

        try {
            $this->client()->run('getThings');
            self::fail('No exception');
        } catch (UnknownProblemException $e) {
            self::assertSame('teapot', $e->getProblemCode());
            self::assertSame(418, $e->getStatus());
            self::assertSame('req_tea', $e->getRequestId());
        }
    }

    public function testAnAnswerThatIsNoProblemDocumentIsAnUnexpectedResponse(): void
    {
        $this->http->enqueue(new Response(502, ['Content-Type' => 'text/html', 'X-Request-Id' => 'req_proxy'], '<html>Bad gateway</html>'));

        try {
            $this->client()->run('getThings');
            self::fail('No exception');
        } catch (UnexpectedResponseException $e) {
            self::assertSame(502, $e->getStatus());
            self::assertSame('req_proxy', $e->getRequestId());
            self::assertInstanceOf(MentionApiException::class, $e);
        }
    }

    public function testASuccessWithoutDataIsAnUnexpectedResponse(): void
    {
        $this->http->enqueue(new Response(200, ['Content-Type' => 'application/json'], '{"hello":"world"}'));

        $this->expectException(UnexpectedResponseException::class);

        $this->client()->run('getThings');
    }
}
