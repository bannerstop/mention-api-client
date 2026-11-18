<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Auth;

use Bannerstop\MentionApi\Exception\TokenRequestException;
use Psr\Clock\ClockInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Fetches an access token with the client-credentials grant, keeps it in the cache (and in memory) and renews it
 * shortly before it expires or when the server has refused it.
 *
 * @internal
 */
final class ClientCredentialsAuthenticator implements Authenticator
{
    /** Seconds before the expiry at which a token is no longer used. */
    private const MARGIN = 30;

    private string $tokenUrl;
    private string $clientId;
    private string $clientSecret;
    private string $scope;
    private ClientInterface $http;
    private RequestFactoryInterface $requestFactory;
    private StreamFactoryInterface $streamFactory;
    private ?CacheInterface $cache;
    private ClockInterface $clock;
    private ?string $token = null;
    private int $tokenExpiresAt = 0;

    public function __construct(
        string $tokenUrl,
        string $clientId,
        string $clientSecret,
        string $scope,
        ClientInterface $http,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        ?CacheInterface $cache,
        ClockInterface $clock
    ) {
        $this->tokenUrl = $tokenUrl;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->scope = $scope;
        $this->http = $http;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->cache = $cache;
        $this->clock = $clock;
    }

    public function getAuthorizationHeader(): string
    {
        $now = $this->clock->now()->getTimestamp();
        if (null === $this->token || $this->tokenExpiresAt <= $now) {
            $this->token = null;
            $this->loadFromCache($now);
        }
        if (null === $this->token) {
            $this->fetch($now);
        }

        return self::bearer((string) $this->token);
    }

    public function reject(string $authorizationHeader): bool
    {
        if (null !== $this->token && self::bearer($this->token) === $authorizationHeader) {
            $this->token = null;
        }
        if (null !== $this->cache) {
            $entry = $this->cache->get($this->cacheKey());
            // Another process may have stored a newer token in the meantime: keep that one.
            if (\is_array($entry) && isset($entry['access_token']) && \is_string($entry['access_token']) && self::bearer($entry['access_token']) === $authorizationHeader) {
                $this->cache->delete($this->cacheKey());
            }
        }

        return true;
    }

    private static function bearer(string $token): string
    {
        return 'Bearer ' . $token;
    }

    private function cacheKey(): string
    {
        return 'mention_api_token_' . sha1($this->tokenUrl . "\0" . $this->clientId . "\0" . $this->scope);
    }

    private function loadFromCache(int $now): void
    {
        if (null === $this->cache) {
            return;
        }
        $entry = $this->cache->get($this->cacheKey());
        if (\is_array($entry) && isset($entry['access_token'], $entry['expires_at']) && \is_string($entry['access_token']) && \is_int($entry['expires_at']) && $entry['expires_at'] > $now) {
            $this->token = $entry['access_token'];
            $this->tokenExpiresAt = $entry['expires_at'];
        }
    }

    private function fetch(int $now): void
    {
        $request = $this->requestFactory->createRequest('POST', $this->tokenUrl)
            ->withHeader('Accept', 'application/json')
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($this->streamFactory->createStream(http_build_query([
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => $this->scope,
            ], '', '&')));
        try {
            $response = $this->http->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new TokenRequestException('The token endpoint could not be reached: ' . $e->getMessage(), $e);
        }
        $document = json_decode((string) $response->getBody(), true);
        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            $error = \is_array($document) && isset($document['error']) && \is_string($document['error']) ? ' (' . $document['error'] . ')' : '';

            throw new TokenRequestException(sprintf('The token endpoint answered with status %d%s.', $status, $error));
        }
        if (!\is_array($document) || !isset($document['access_token']) || !\is_string($document['access_token']) || '' === $document['access_token']) {
            throw new TokenRequestException('The token endpoint answered without an access token.');
        }

        $expiresIn = isset($document['expires_in']) && is_numeric($document['expires_in']) ? (int) $document['expires_in'] : 60;
        $lifetime = max(1, $expiresIn - min(self::MARGIN, intdiv($expiresIn, 2)));
        $this->token = $document['access_token'];
        $this->tokenExpiresAt = $now + $lifetime;
        if (null !== $this->cache) {
            $this->cache->set($this->cacheKey(), ['access_token' => $this->token, 'expires_at' => $this->tokenExpiresAt], $lifetime);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['tokenUrl' => $this->tokenUrl, 'clientId' => $this->clientId];
    }
}
