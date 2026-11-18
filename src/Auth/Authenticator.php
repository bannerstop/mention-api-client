<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Auth;

/**
 * @internal
 */
interface Authenticator
{
    /**
     * The value of the Authorization header of the next call.
     */
    public function getAuthorizationHeader(): string;

    /**
     * Forgets the credential behind an Authorization header that the server refused.
     *
     * @return bool whether the next call may carry a different header, so that sending the call again can help
     */
    public function reject(string $authorizationHeader): bool;
}
