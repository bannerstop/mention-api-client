<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Tests;

use Bannerstop\MentionApi\MentionApiConfig;
use Bannerstop\MentionApi\Tests\Fixtures\Answers;
use Bannerstop\MentionApi\Tests\Fixtures\TimeoutAwareHttpClient;

final class TimeoutTest extends ClientTestCase
{
    public function testTheDefaultTimeoutIsThirtySeconds(): void
    {
        $http = new TimeoutAwareHttpClient($this->http);
        $this->http->enqueue(Answers::rows([]));

        $this->client(null, $http)->run('getThings');

        self::assertSame([30.0], $http->timeouts);
        self::assertSame(30.0, MentionApiConfig::forApiKey(self::BASE_URL, 'key')->getTimeout());
    }

    public function testTheTimeoutCanBeChanged(): void
    {
        $http = new TimeoutAwareHttpClient($this->http);
        $this->http->enqueue(Answers::rows([]));

        $this->client(MentionApiConfig::forApiKey(self::BASE_URL, 'key')->withTimeout(2.5), $http)->run('getThings');

        self::assertSame([2.5], $http->timeouts);
    }

    public function testTheTimeoutAlsoLimitsTheTokenRequest(): void
    {
        $http = new TimeoutAwareHttpClient($this->http);
        $this->http
            ->enqueue(Answers::json(['access_token' => 'token-1', 'expires_in' => 300]))
            ->enqueue(Answers::rows([]));
        $config = MentionApiConfig::forClientCredentials(self::BASE_URL, 'https://sso.example.com/token', 'my-app', 'secret')->withTimeout(8.0);

        $this->client($config, $http)->run('getThings');

        self::assertSame([8.0, 8.0], $http->timeouts);
    }

    public function testAnHttpClientThatDoesNotKnowTimeoutsIsUsedAsItIs(): void
    {
        $this->http->enqueue(Answers::rows([['id' => 1]]));

        $result = $this->client(MentionApiConfig::forApiKey(self::BASE_URL, 'key')->withTimeout(5.0))->run('getThings');

        self::assertCount(1, $result);
    }

    public function testAZeroTimeoutIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        MentionApiConfig::forApiKey(self::BASE_URL, 'key')->withTimeout(0.0);
    }
}
