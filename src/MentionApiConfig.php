<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi;

/**
 * Where mention-api lives and how to authenticate: either an API key or a Keycloak client-credentials client.
 * Immutable; secrets are hidden from var_dump().
 */
final class MentionApiConfig
{
    public const DEFAULT_TIMEOUT = 30.0;

    private string $baseUrl;
    private float $timeout = self::DEFAULT_TIMEOUT;
    private string $apiKey;

    private function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * @param string $baseUrl e.g. https://mention-api.example.com (without /v2)
     */
    public static function forApiKey(string $baseUrl, string $apiKey): self
    {
        $config = new self($baseUrl);
        $config->apiKey = $apiKey;

        return $config;
    }

    public function withTimeout(float $seconds): self
    {
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
    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['baseUrl' => $this->baseUrl, 'timeout' => $this->timeout, 'credentials' => '***'];
    }
}
