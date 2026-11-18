<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Generator\Console;

use Bannerstop\MentionApi\Generator\GeneratorException;
use Bannerstop\MentionApi\MentionApiClient;
use Bannerstop\MentionApi\MentionApiConfig;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Builds the client the generator fetches the OpenAPI document with from environment variables:
 *
 *   MENTION_API_URL                          the base URL, without /v2
 *   MENTION_API_KEY                          the API key of your Consumer, or
 *   MENTION_API_TOKEN_URL, MENTION_API_CLIENT_ID, MENTION_API_CLIENT_SECRET [, MENTION_API_SCOPE]
 *
 * It uses Guzzle 7 or Symfony HttpClient, whichever is installed.
 *
 * @internal
 */
final class EnvironmentClient
{
    /**
     * @param array<string, string> $env
     *
     * @throws GeneratorException
     */
    public static function create(array $env): MentionApiClient
    {
        $config = self::config($env);
        [$http, $requests, $streams] = self::httpStack();

        return new MentionApiClient($config, $http, $requests, $streams);
    }

    /**
     * @param array<string, string> $env
     */
    public static function config(array $env): MentionApiConfig
    {
        $url = $env['MENTION_API_URL'] ?? '';
        if ('' === $url) {
            throw new GeneratorException('Cannot fetch the OpenAPI document: set MENTION_API_URL and a credential, pass --client with a file that returns your configured client, or pass --source with a file.');
        }
        if ('' !== ($env['MENTION_API_KEY'] ?? '')) {
            return MentionApiConfig::forApiKey($url, $env['MENTION_API_KEY']);
        }
        $oauth = ['MENTION_API_TOKEN_URL', 'MENTION_API_CLIENT_ID', 'MENTION_API_CLIENT_SECRET'];
        $missing = array_values(array_filter($oauth, static function (string $name) use ($env): bool {
            return '' === ($env[$name] ?? '');
        }));
        if ([] !== $missing) {
            throw new GeneratorException('MENTION_API_URL is set, but there is no credential: set MENTION_API_KEY, or ' . implode(', ', $oauth) . ' (missing: ' . implode(', ', $missing) . ').');
        }

        return MentionApiConfig::forClientCredentials($url, $env['MENTION_API_TOKEN_URL'], $env['MENTION_API_CLIENT_ID'], $env['MENTION_API_CLIENT_SECRET'], '' === ($env['MENTION_API_SCOPE'] ?? '') ? MentionApiConfig::DEFAULT_SCOPE : $env['MENTION_API_SCOPE']);
    }

    /**
     * @return array{0: ClientInterface, 1: RequestFactoryInterface, 2: StreamFactoryInterface}
     */
    private static function httpStack(): array
    {
        if (class_exists('GuzzleHttp\Client') && class_exists('GuzzleHttp\Psr7\HttpFactory')) {
            /** @var ClientInterface $http */
            $http = new \GuzzleHttp\Client(['timeout' => MentionApiConfig::DEFAULT_TIMEOUT, 'connect_timeout' => 10]);
            $factory = new \GuzzleHttp\Psr7\HttpFactory();

            return [$http, $factory, $factory];
        }
        if (class_exists('Symfony\Component\HttpClient\Psr18Client')) {
            /** @var \Symfony\Component\HttpClient\Psr18Client $client */
            $client = new \Symfony\Component\HttpClient\Psr18Client();

            return [$client, $client, $client];
        }

        throw new GeneratorException('No HTTP client found to fetch the document with: install guzzlehttp/guzzle or symfony/http-client and nyholm/psr7, or pass --client with a file that returns your configured client.');
    }
}
