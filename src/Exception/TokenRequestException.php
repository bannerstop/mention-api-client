<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Exception;

/**
 * The access token could not be fetched from the token endpoint (client-credentials authentication). The message never
 * contains the client secret.
 */
final class TokenRequestException extends MentionApiException
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, null, 0, $previous);
    }
}
