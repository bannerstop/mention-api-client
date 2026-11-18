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
}
