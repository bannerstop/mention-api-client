<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Auth;

/**
 * @internal
 */
final class ApiKeyAuthenticator implements Authenticator
{
    /** @var string */
    private $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function getAuthorizationHeader(): string
    {
        return 'Bearer ' . $this->apiKey;
    }

    public function reject(string $authorizationHeader): bool
    {
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [];
    }
}
