<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Tests;

use Bannerstop\MentionApi\Http\SymfonyTimeoutClient;
use Bannerstop\MentionApi\MentionApiConfig;
use Nyholm\Psr7\Request;
use Psr\Http\Client\NetworkExceptionInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SymfonyTimeoutClientTest extends ClientTestCase
{
    /** @var list<array<string, mixed>> the options of every request that reached the Symfony client */
    private array $options = [];

    /**
     * A real Symfony MockHttpClient that answers with the given responses and records the options.
     *
     * @param list<MockResponse> $answers
     */
    private function symfony(array $answers): MockHttpClient
    {
        return new MockHttpClient(function (string $method, string $url, array $options) use (&$answers): MockResponse {
            $this->options[] = $options;

            return array_shift($answers) ?? new MockResponse('', ['http_code' => 500]);
        }, self::BASE_URL);
    }

    private static function answer(string $body, int $status = 200): MockResponse
    {
        return new MockResponse($body, ['http_code' => $status, 'response_headers' => ['Content-Type: application/json']]);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private static function rows(array $rows): MockResponse
    {
        return self::answer(json_encode(['data' => $rows, 'meta' => ['query' => 'getThings', 'version' => 3, 'rows' => \count($rows), 'duration_ms' => 12]], JSON_THROW_ON_ERROR));
    }

    public function testItSendsTheRequestWithTheTimeout(): void
    {
        $client = (new SymfonyTimeoutClient($this->symfony([self::answer('{}')])))->withTimeout(2.5);

        $client->sendRequest(new Request('GET', self::BASE_URL . '/v2/queries'));

        self::assertSame(2.5, $this->options[0]['timeout']);
    }

    public function testTheTimeoutAlsoLimitsTheWholeCall(): void
    {
        $client = (new SymfonyTimeoutClient($this->symfony([self::answer('{}')])))->withTimeout(2.5);

        $client->sendRequest(new Request('GET', self::BASE_URL . '/v2/queries'));

        self::assertSame(2.5, $this->options[0]['max_duration']);
    }

    public function testAnErrorStatusIsAnAnswerAndNotAnException(): void
    {
        $client = (new SymfonyTimeoutClient($this->symfony([self::answer('{"title":"Problem"}', 401)])))->withTimeout(5.0);

        $response = $client->sendRequest(new Request('GET', self::BASE_URL . '/v2/queries'));

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('{"title":"Problem"}', (string) $response->getBody());
    }

    public function testAConnectionErrorIsANetworkException(): void
    {
        $client = (new SymfonyTimeoutClient($this->symfony([new MockResponse('', ['error' => 'Connection timed out'])])))->withTimeout(5.0);

        $this->expectException(NetworkExceptionInterface::class);

        $client->sendRequest(new Request('GET', self::BASE_URL . '/v2/queries'));
    }

    public function testTheTimeoutOfTheConfigurationReachesSymfony(): void
    {
        $http = new SymfonyTimeoutClient($this->symfony([self::rows([])]));

        $this->client(MentionApiConfig::forApiKey(self::BASE_URL, 'key')->withTimeout(2.5), $http)->run('getThings');

        self::assertSame(2.5, $this->options[0]['timeout']);
    }

    public function testTheDefaultTimeoutIsThirtySeconds(): void
    {
        $http = new SymfonyTimeoutClient($this->symfony([self::rows([['id' => 1]])]));

        $result = $this->client(null, $http)->run('getThings');

        self::assertCount(1, $result);
        self::assertSame(30.0, $this->options[0]['timeout']);
        self::assertSame(30.0, $this->options[0]['max_duration']);
    }

    public function testTheTimeoutAlsoLimitsTheTokenRequest(): void
    {
        $http = new SymfonyTimeoutClient($this->symfony([self::answer('{"access_token":"token-1","expires_in":300}'), self::rows([])]));
        $config = MentionApiConfig::forClientCredentials(self::BASE_URL, 'https://sso.example.com/token', 'my-app', 'secret')->withTimeout(8.0);

        $this->client($config, $http)->run('getThings');

        self::assertSame([8.0, 8.0], array_column($this->options, 'timeout'));
    }

    public function testAConnectionErrorIsRetriedLikeWithAnyOtherClient(): void
    {
        $http = new SymfonyTimeoutClient($this->symfony([new MockResponse('', ['error' => 'Connection timed out']), self::rows([['id' => 1]])]));

        $result = $this->client(null, $http)->run('getThings');

        self::assertCount(1, $result);
        self::assertSame([1.0], $this->sleeper->sleeps);
    }

    public function testARejectedTokenIsRefreshedWithSymfonyToo(): void
    {
        $http = new SymfonyTimeoutClient($this->symfony([
            self::answer('{"access_token":"token-1","expires_in":300}'),
            self::answer('{"type":"urn:bannerstop:mention-api:problem:unauthorized","title":"Unauthorized","status":401,"detail":"x","request_id":"req_1"}', 401),
            self::answer('{"access_token":"token-2","expires_in":300}'),
            self::rows([['id' => 1]]),
        ]));
        $config = MentionApiConfig::forClientCredentials(self::BASE_URL, 'https://sso.example.com/token', 'my-app', 'secret');

        $result = $this->client($config, $http)->run('getThings');

        self::assertCount(1, $result);
        self::assertCount(4, $this->options);
    }
}
