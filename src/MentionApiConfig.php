<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi;

/**
 * Where mention-api lives and how to authenticate: with an API key or with OAuth 2.0 client credentials.
 * Immutable; secrets are hidden from var_dump().
 */
final class MentionApiConfig
{
    public const DEFAULT_TIMEOUT = 30.0;
    public const DEFAULT_SCOPE = 'mention-api';

    private string $baseUrl;
    private float $timeout = self::DEFAULT_TIMEOUT;
    private ?string $apiKey = null;
    private ?string $tokenUrl = null;
    private ?string $clientId = null;
    private ?string $clientSecret = null;
    private string $scope = self::DEFAULT_SCOPE;

    private function __construct(string $baseUrl)
    {
        self::assertHttpUrl($baseUrl, 'The base URL');
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    private static function assertHttpUrl(string $url, string $what): void
    {
        if (1 !== preg_match('#^https?://[^/\s]#i', $url)) {
            throw new \InvalidArgumentException($what . ' must be an http(s) URL.');
        }
    }

    private static function assertNotEmpty(string $value, string $what): void
    {
        if ('' === $value) {
            throw new \InvalidArgumentException($what . ' must not be empty.');
        }
    }

    /**
     * Authenticate with the API key of your Consumer.
     *
     * @param string $baseUrl e.g. https://mention-api.example.com (without /v2)
     */
    public static function forApiKey(string $baseUrl, string $apiKey): self
    {
        self::assertNotEmpty($apiKey, 'The API key');
        $config = new self($baseUrl);
        $config->apiKey = $apiKey;

        return $config;
    }

    /**
     * Authenticate with the OAuth 2.0 client-credentials grant. The access token is fetched when needed, cached and
     * renewed shortly before it expires.
     *
     * @param string $baseUrl  e.g. https://mention-api.example.com (without /v2)
     * @param string $tokenUrl the token endpoint of the OAuth 2.0 server, e.g. https://sso.example.com/realms/example/protocol/openid-connect/token
     * @param string $scope    the client scope that adds the mention-api audience to the token
     */
    public static function forClientCredentials(string $baseUrl, string $tokenUrl, string $clientId, string $clientSecret, string $scope = self::DEFAULT_SCOPE): self
    {
        self::assertHttpUrl($tokenUrl, 'The token URL');
        self::assertNotEmpty($clientId, 'The client id');
        self::assertNotEmpty($clientSecret, 'The client secret');
        $config = new self($baseUrl);
        $config->tokenUrl = $tokenUrl;
        $config->clientId = $clientId;
        $config->clientSecret = $clientSecret;
        $config->scope = $scope;

        return $config;
    }

    /**
     * How long to wait for an answer, in seconds. It is handed to HTTP clients that implement TimeoutAware; any other
     * PSR-18 client has to be configured with its own timeout.
     */
    public function withTimeout(float $seconds): self
    {
        if ($seconds <= 0) {
            throw new \InvalidArgumentException('The timeout must be greater than zero.');
        }
        $config = clone $this;
        $config->timeout = $seconds;

        return $config;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getTimeout(): float
    {
        return $this->timeout;
    }

    /**
     * @internal
     */
    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    /**
     * @internal
     */
    public function getTokenUrl(): ?string
    {
        return $this->tokenUrl;
    }

    /**
     * @internal
     */
    public function getClientId(): ?string
    {
        return $this->clientId;
    }

    /**
     * @internal
     */
    public function getClientSecret(): ?string
    {
        return $this->clientSecret;
    }

    /**
     * @internal
     */
    public function getScope(): string
    {
        return $this->scope;
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['baseUrl' => $this->baseUrl, 'timeout' => $this->timeout, 'credentials' => '***'];
    }
}
