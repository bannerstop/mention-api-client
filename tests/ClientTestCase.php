<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Tests;

use Bannerstop\MentionApi\MentionApiClient;
use Bannerstop\MentionApi\MentionApiConfig;
use Bannerstop\MentionApi\Tests\Fixtures\ArrayCache;
use Bannerstop\MentionApi\Tests\Fixtures\FakeHttpClient;
use Bannerstop\MentionApi\Tests\Fixtures\FrozenClock;
use Bannerstop\MentionApi\Tests\Fixtures\RecordingSleeper;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;

abstract class ClientTestCase extends TestCase
{
    protected const BASE_URL = 'https://mention-api.example.com';

    /** @var FakeHttpClient */
    protected $http;
    /** @var ArrayCache */
    protected $cache;
    /** @var FrozenClock */
    protected $clock;
    /** @var RecordingSleeper */
    protected $sleeper;

    protected function setUp(): void
    {
        $this->http = new FakeHttpClient();
        $this->cache = new ArrayCache();
        $this->clock = new FrozenClock();
        $this->sleeper = new RecordingSleeper();
    }

    protected function client(?MentionApiConfig $config = null, ?ClientInterface $http = null): MentionApiClient
    {
        $factory = new Psr17Factory();

        return new MentionApiClient(
            $config ?? MentionApiConfig::forApiKey(self::BASE_URL, 'mapi_ck_example'),
            $http ?? $this->http,
            $factory,
            $factory,
            $this->cache,
            $this->clock,
            $this->sleeper
        );
    }
}
