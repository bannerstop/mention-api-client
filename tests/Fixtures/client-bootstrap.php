<?php

declare(strict_types=1);

use Bannerstop\MentionApi\MentionApiClient;
use Bannerstop\MentionApi\MentionApiConfig;
use Bannerstop\MentionApi\Tests\Fixtures\Answers;
use Bannerstop\MentionApi\Tests\Fixtures\FakeHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;

// A client for the console tests: it answers with the sample document and records the requests in $GLOBALS.
$http = new FakeHttpClient();
$http->enqueue(Answers::json(json_decode((string) file_get_contents(__DIR__ . '/openapi.json'), true)));
$GLOBALS['mention_api_client_test_http'] = $http;
$factory = new Psr17Factory();

return new MentionApiClient(MentionApiConfig::forApiKey('https://mention-api.example.com', 'mapi_ck_example'), $http, $factory, $factory);
