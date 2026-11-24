<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Tests;

use Bannerstop\MentionApi\Exception\TokenRequestException;
use Bannerstop\MentionApi\Exception\UnauthorizedException;
use Bannerstop\MentionApi\Exception\UnavailableException;
use Bannerstop\MentionApi\MentionApiConfig;
use Bannerstop\MentionApi\Tests\Fixtures\Answers;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class TokenRefreshTest extends ClientTestCase
{
    private const TOKEN_URL = 'https://sso.example.com/realms/example/protocol/openid-connect/token';

    private static function credentials(): MentionApiConfig
    {
        return MentionApiConfig::forClientCredentials(self::BASE_URL, self::TOKEN_URL, 'my-app', 'the-client-secret');
    }

    private static function token(string $accessToken): ResponseInterface
    {
        return Answers::json(['access_token' => $accessToken, 'expires_in' => 300, 'token_type' => 'Bearer']);
    }

    private static function rejected(): ResponseInterface
    {
        return Answers::problem(401, 'unauthorized', 'Unauthorized', 'The token is not valid.');
    }

    /**
     * @return list<string> what each request was: the Authorization header, or "token request" for the token endpoint
     */
    private function sent(): array
    {
        return array_map(static function (RequestInterface $request): string {
            return (string) $request->getUri() === self::TOKEN_URL ? 'token request' : $request->getHeaderLine('Authorization');
        }, $this->http->requests);
    }

    public function testARejectedTokenIsReplacedAndTheCallIsSentAgain(): void
    {
        $this->http
            ->enqueue(self::token('token-1'))
            ->enqueue(self::rejected())
            ->enqueue(self::token('token-2'))
            ->enqueue(Answers::rows([['id' => 1]]));

        $result = $this->client(self::credentials())->run('getThings');

        self::assertSame([['id' => 1]], $result->getRows());
        self::assertSame(['token request', 'Bearer token-1', 'token request', 'Bearer token-2'], $this->sent());
    }

    public function testTheNewTokenIsKeptForLaterCalls(): void
    {
        $this->http
            ->enqueue(self::token('token-1'))
            ->enqueue(self::rejected())
            ->enqueue(self::token('token-2'))
            ->enqueue(Answers::rows([]));
        $this->client(self::credentials())->run('getThings');

        $this->client(self::credentials())->listQueries();

        self::assertSame(['token request', 'Bearer token-1', 'token request', 'Bearer token-2', 'Bearer token-2'], $this->sent());
    }

    public function testASecondRejectionIsThrown(): void
    {
        $this->http
            ->enqueue(self::token('token-1'))
            ->enqueue(self::rejected())
            ->enqueue(self::token('token-2'))
            ->enqueue(self::rejected());

        try {
            $this->client(self::credentials())->run('getThings');
            self::fail('No exception');
        } catch (UnauthorizedException $e) {
            self::assertSame('req_problem', $e->getRequestId());
        }
        self::assertSame(['token request', 'Bearer token-1', 'token request', 'Bearer token-2'], $this->sent());
    }

    public function testAnApiKeyThatIsRejectedIsThrownAtOnce(): void
    {
        $this->http->enqueue(self::rejected());

        try {
            $this->client()->run('getThings');
            self::fail('No exception');
        } catch (UnauthorizedException $e) {
            self::assertCount(1, $this->http->requests);
        }
    }

    public function testARoutineIsSentAgainOnceWithTheNewToken(): void
    {
        $this->http
            ->enqueue(self::token('token-1'))
            ->enqueue(self::rejected())
            ->enqueue(self::token('token-2'))
            ->enqueue(Answers::json(['data' => ['action' => 'updated'], 'meta' => ['routine' => 'setContactEmail', 'rows' => 1, 'duration_ms' => 5]]));

        $result = $this->client(self::credentials())->runRoutine('setContactEmail', ['contactId' => 7]);

        self::assertSame('updated', $result->getAction());
        self::assertSame(['token request', 'Bearer token-1', 'token request', 'Bearer token-2'], $this->sent());
        self::assertSame(['parameters' => ['contactId' => 7]], $this->http->lastJsonBody());
    }

    public function testARoutineIsNotSentAgainForAnythingButARejectedToken(): void
    {
        $this->http
            ->enqueue(self::token('token-1'))
            ->enqueue(Answers::problem(503, 'mention-unavailable'));

        try {
            $this->client(self::credentials())->runRoutine('setContactEmail');
            self::fail('No exception');
        } catch (UnavailableException $e) {
            self::assertCount(2, $this->http->requests);
        }
    }

    public function testTheRefreshDoesNotUseUpTheRetriesOfAQuery(): void
    {
        $this->http
            ->enqueue(self::token('token-1'))
            ->enqueue(self::rejected())
            ->enqueue(self::token('token-2'))
            ->enqueue(Answers::problem(429, 'too-many-requests'))
            ->enqueue(Answers::problem(429, 'too-many-requests'))
            ->enqueue(Answers::rows([['id' => 1]]));

        $result = $this->client(self::credentials())->run('getThings');

        self::assertSame([['id' => 1]], $result->getRows());
        self::assertSame(['token request', 'Bearer token-1', 'token request', 'Bearer token-2', 'Bearer token-2', 'Bearer token-2'], $this->sent());
    }

    public function testTheRetriesOfAQueryDoNotUseUpTheRefresh(): void
    {
        $this->http
            ->enqueue(self::token('token-1'))
            ->enqueue(Answers::problem(429, 'too-many-requests'))
            ->enqueue(Answers::problem(429, 'too-many-requests'))
            ->enqueue(self::rejected())
            ->enqueue(self::token('token-2'))
            ->enqueue(Answers::rows([['id' => 1]]));

        $result = $this->client(self::credentials())->run('getThings');

        self::assertSame([['id' => 1]], $result->getRows());
        self::assertSame(['token request', 'Bearer token-1', 'Bearer token-1', 'Bearer token-1', 'token request', 'Bearer token-2'], $this->sent());
    }

    public function testAFailedRefreshOfTheTokenIsReported(): void
    {
        $this->http
            ->enqueue(self::token('token-1'))
            ->enqueue(self::rejected())
            ->enqueue(Answers::json(['error' => 'invalid_client'], 401));

        $this->expectException(TokenRequestException::class);

        $this->client(self::credentials())->run('getThings');
    }

    public function testATokenThatAnotherProcessStoredInTheMeantimeIsUsedInsteadOfFetchingOne(): void
    {
        $this->http
            ->enqueue(self::token('token-1'))
            ->enqueue(Answers::rows([]))
            ->enqueue(self::rejected())
            ->enqueue(Answers::rows([]));
        $client = $this->client(self::credentials());
        $client->run('getThings');
        $this->cache->set((string) key($this->cache->entries), ['access_token' => 'token-2', 'expires_at' => $this->clock->now()->getTimestamp() + 200]);

        $client->run('getThings');

        self::assertSame(['token request', 'Bearer token-1', 'Bearer token-1', 'Bearer token-2'], $this->sent());
    }
}
