<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Result;

/**
 * What every successful call has in common: the request id of the call.
 */
abstract class Result
{
    private ?string $requestId;

    public function __construct(?string $requestId)
    {
        $this->requestId = $requestId;
    }

    /**
     * The id mention-api gave this call (header X-Request-Id); quote it when you ask about the call.
     */
    public function getRequestId(): ?string
    {
        return $this->requestId;
    }
}
