<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Tests;

use Bannerstop\MentionApi\Http\GuzzleTimeoutClient;
use Bannerstop\MentionApi\MentionApiConfig;
use Bannerstop\MentionApi\Tests\Fixtures\Answers;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

final class GuzzleTimeoutClientTest extends ClientTestCase
{
    /** @var list<array<string, mixed>> the options of every request that reached the Guzzle handler */
    private $options = [];

    /**
     * A real Guzzle client whose handler answers with the given responses or exceptions and records the options.
     *
     * @param list<\Psr\Http\Message\ResponseInterface|\Throwable> $answers
     * @param array<string, mixed>                                  $config
     */
    private function guzzle(array $answers, array $config = []): Client
    {
        $queue = array_map(function ($answer): callable {
            return function (RequestInterface $request, array $options) use ($answer) {
                $this->options[] = $options;

                return $answer;
            };
        }, $answers);

        return new Client(['handler' => HandlerStack::create(new MockHandler($queue))] + $config);
    }

    public function testItSendsTheRequestWithTheTimeout(): void
    {
        $client = (new GuzzleTimeoutClient($this->guzzle([Answers::rows([])])))->withTimeout(2.5);

        $client->sendRequest(new Request('GET', self::BASE_URL . '/v2/queries'));

        self::assertSame(2.5, $this->options[0]['timeout']);
    }

    public function testAnErrorStatusIsAnAnswerAndNotAnException(): void
    {
        $client = (new GuzzleTimeoutClient($this->guzzle([Answers::problem(401, 'unauthorized')])))->withTimeout(5.0);

        $response = $client->sendRequest(new Request('GET', self::BASE_URL . '/v2/queries'));

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('req_problem', $response->getHeaderLine('X-Request-Id'));
    }

    public function testARedirectIsAnAnswerToo(): void
    {
        $client = (new GuzzleTimeoutClient($this->guzzle([new Response(302, ['Location' => 'https://elsewhere.example.com/']), Answers::rows([])])))->withTimeout(5.0);

        $response = $client->sendRequest(new Request('GET', self::BASE_URL . '/v2/queries'));

        self::assertSame(302, $response->getStatusCode());
    }

    public function testAGuzzleClientGivenToTheMentionClientGetsTheDefaultTimeout(): void
    {
        $guzzle = $this->guzzle([Answers::rows([['id' => 1]])]);

        $result = $this->client(null, $guzzle)->run('getThings');

        self::assertCount(1, $result);
        self::assertSame(30.0, $this->options[0]['timeout']);
    }

    public function testTheTimeoutOfTheConfigurationReachesGuzzle(): void
    {
        $guzzle = $this->guzzle([Answers::rows([])]);

        $this->client(MentionApiConfig::forApiKey(self::BASE_URL, 'key')->withTimeout(2.5), $guzzle)->run('getThings');

        self::assertSame(2.5, $this->options[0]['timeout']);
    }

    public function testTheTimeoutAlsoLimitsTheTokenRequest(): void
    {
        $guzzle = $this->guzzle([Answers::json(['access_token' => 'token-1', 'expires_in' => 300]), Answers::rows([])]);
        $config = MentionApiConfig::forClientCredentials(self::BASE_URL, 'https://sso.example.com/token', 'my-app', 'secret')->withTimeout(8.0);

        $this->client($config, $guzzle)->run('getThings');

        self::assertSame([8.0, 8.0], array_column($this->options, 'timeout'));
    }

    public function testTheTimeoutOfTheConfigurationWinsOverTheOneOfTheGuzzleClient(): void
    {
        $guzzle = $this->guzzle([Answers::rows([])], ['timeout' => 120]);

        $this->client(null, $guzzle)->run('getThings');

        self::assertSame(30.0, $this->options[0]['timeout']);
    }

    public function testAConnectionErrorIsRetriedLikeWithAnyOtherClient(): void
    {
        $request = new Request('GET', self::BASE_URL . '/v2/queries');
        $guzzle = $this->guzzle([new ConnectException('cURL error 28: Operation timed out', $request), Answers::rows([['id' => 1]])]);

        $result = $this->client(null, $guzzle)->run('getThings');

        self::assertCount(1, $result);
        self::assertCount(2, $this->options);
        self::assertSame([1.0], $this->sleeper->sleeps);
    }

    public function testARejectedTokenIsRefreshedWithGuzzleToo(): void
    {
        $guzzle = $this->guzzle([
            Answers::json(['access_token' => 'token-1', 'expires_in' => 300]),
            Answers::problem(401, 'unauthorized'),
            Answers::json(['access_token' => 'token-2', 'expires_in' => 300]),
            Answers::rows([['id' => 1]]),
        ]);
        $config = MentionApiConfig::forClientCredentials(self::BASE_URL, 'https://sso.example.com/token', 'my-app', 'secret');

        $result = $this->client($config, $guzzle)->run('getThings');

        self::assertCount(1, $result);
        self::assertCount(4, $this->options);
    }
}
