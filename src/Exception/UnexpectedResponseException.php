<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Exception;

/**
 * An answer that is neither a result nor a problem document of mention-api, e.g. an error page of a proxy.
 */
final class UnexpectedResponseException extends MentionApiException
{
    private int $status;

    public function __construct(string $message, int $status, ?string $requestId = null)
    {
        parent::__construct($message, $requestId, $status);
        $this->status = $status;
    }

    public function getStatus(): int
    {
        return $this->status;
    }
}
