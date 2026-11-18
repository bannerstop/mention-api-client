<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Exception;

/**
 * No answer came back: the connection failed, timed out or the HTTP client refused the request.
 */
final class TransportException extends MentionApiException
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, null, 0, $previous);
    }
}
