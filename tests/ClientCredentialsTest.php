<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Tests;

use Bannerstop\MentionApi\Exception\TokenRequestException;
use Bannerstop\MentionApi\MentionApiClient;
use Bannerstop\MentionApi\MentionApiConfig;
use Bannerstop\MentionApi\Tests\Fixtures\Answers;
use Bannerstop\MentionApi\Tests\Fixtures\NetworkFailure;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;

final class ClientCredentialsTest extends ClientTestCase
{
    private const TOKEN_URL = 'https://sso.example.com/realms/example/protocol/openid-connect/token';

    private static function config(): MentionApiConfig
    {
        return MentionApiConfig::forClientCredentials(self::BASE_URL, self::TOKEN_URL, 'my-app', 'the-client-secret');
    }

    private static function token(string $accessToken = 'token-1', int $expiresIn = 300): ResponseInterface
    {
        return Answers::json(['access_token' => $accessToken, 'expires_in' => $expiresIn, 'token_type' => 'Bearer']);
    }

    public function testItFetchesATokenWithTheClientCredentialsGrantAndSendsItAsBearerToken(): void
    {
        $this->http->enqueue(self::token('token-1'))->enqueue(Answers::rows([['id' => 1]]));

        $result = $this->client(self::config())->run('getThings');

        self::assertSame([['id' => 1]], $result->getRows());
        self::assertCount(2, $this->http->requests);
        $tokenRequest = $this->http->requests[0];
        self::assertSame('POST', $tokenRequest->getMethod());
        self::assertSame(self::TOKEN_URL, (string) $tokenRequest->getUri());
        self::assertSame('application/x-www-form-urlencoded', $tokenRequest->getHeaderLine('Content-Type'));
        parse_str((string) $tokenRequest->getBody(), $form);
        self::assertSame(['grant_type' => 'client_credentials', 'client_id' => 'my-app', 'client_secret' => 'the-client-secret', 'scope' => 'mention-api'], $form);
        self::assertSame('Bearer token-1', $this->http->requests[1]->getHeaderLine('Authorization'));
    }

    public function testTheScopeCanBeChanged(): void
    {
        $this->http->enqueue(self::token())->enqueue(Answers::rows([]));

        $this->client(MentionApiConfig::forClientCredentials(self::BASE_URL, self::TOKEN_URL, 'my-app', 'secret', 'other-scope'))->run('getThings');

        parse_str((string) $this->http->requests[0]->getBody(), $form);
        self::assertSame('other-scope', $form['scope']);
    }

    public function testTheTokenIsCachedForLaterCalls(): void
    {
        $this->http->enqueue(self::token('token-1'))->enqueue(Answers::rows([]));
        $client = $this->client(self::config());

        $client->run('getThings');
        $client->run('getThings');
        $client->listQueries();

        $tokenRequests = array_filter($this->http->requests, static fn ($request): bool => (string) $request->getUri() === self::TOKEN_URL);
        self::assertCount(1, $tokenRequests);
        self::assertCount(4, $this->http->requests);
    }

    public function testTheCachedTokenIsSharedWithOtherInstances(): void
    {
        $this->http->enqueue(self::token('token-1'))->enqueue(Answers::rows([]));
        $this->client(self::config())->run('getThings');

        $this->client(self::config())->run('getThings');

        self::assertSame('Bearer token-1', $this->http->lastRequest()->getHeaderLine('Authorization'));
        self::assertCount(3, $this->http->requests);
    }

    public function testTheCacheEntryExpiresBeforeTheToken(): void
    {
        $this->http->enqueue(self::token('token-1', 300))->enqueue(Answers::rows([]));

        $this->client(self::config())->run('getThings');

        $entries = array_values($this->cache->entries);
        self::assertCount(1, $entries);
        self::assertSame(270, $entries[0][1], 'the cache lifetime is the token lifetime minus a 30 s margin');
    }

    public function testAnExpiringTokenIsReplacedBeforeItExpires(): void
    {
        $this->http
            ->enqueue(self::token('token-1', 300))
            ->enqueue(Answers::rows([]))
            ->enqueue(Answers::rows([]))
            ->enqueue(self::token('token-2', 300))
            ->enqueue(Answers::rows([]));
        $client = $this->client(self::config());

        $client->run('getThings');
        $this->clock->advance(269);
        $client->run('getThings');
        self::assertSame('Bearer token-1', $this->http->lastRequest()->getHeaderLine('Authorization'));
        $this->clock->advance(2);
        $client->run('getThings');

        self::assertSame('Bearer token-2', $this->http->lastRequest()->getHeaderLine('Authorization'));
    }

    public function testWithoutACacheTheTokenIsKeptByTheClient(): void
    {
        $this->http->enqueue(self::token('token-1'))->enqueue(Answers::rows([]));
        $factory = new Psr17Factory();
        $client = new MentionApiClient(self::config(), $this->http, $factory, $factory, null, $this->clock, $this->sleeper);

        $client->run('getThings');
        $client->run('getThings');

        self::assertCount(3, $this->http->requests);
    }

    public function testARefusedTokenRequestFailsWithoutCallingTheApi(): void
    {
        $this->http->enqueue(Answers::json(['error' => 'unauthorized_client', 'error_description' => 'Invalid client secret'], 401));

        try {
            $this->client(self::config())->run('getThings');
            self::fail('No exception');
        } catch (TokenRequestException $e) {
            self::assertStringContainsString('401', $e->getMessage());
            self::assertStringContainsString('unauthorized_client', $e->getMessage());
            self::assertStringNotContainsString('the-client-secret', $e->getMessage());
        }
        self::assertCount(1, $this->http->requests);
    }

    public function testATokenEndpointThatCannotBeReachedFailsToo(): void
    {
        $failure = new NetworkFailure('Connection refused');
        $this->http->enqueue($failure);

        try {
            $this->client(self::config())->run('getThings');
            self::fail('No exception');
        } catch (TokenRequestException $e) {
            self::assertSame($failure, $e->getPrevious());
        }
    }

    public function testATokenAnswerWithoutAccessTokenFails(): void
    {
        $this->http->enqueue(Answers::json(['token_type' => 'Bearer']));

        $this->expectException(TokenRequestException::class);

        $this->client(self::config())->run('getThings');
    }

    public function testTheSecretsStayOutOfVarDump(): void
    {
        ob_start();
        var_dump(self::config());
        $dump = (string) ob_get_clean();

        self::assertStringNotContainsString('the-client-secret', $dump);
    }

    public function testAnApiKeyIsNotRevealedEither(): void
    {
        ob_start();
        var_dump(MentionApiConfig::forApiKey(self::BASE_URL, 'mapi_ck_topsecret'));
        $dump = (string) ob_get_clean();

        self::assertStringNotContainsString('mapi_ck_topsecret', $dump);
    }
}
