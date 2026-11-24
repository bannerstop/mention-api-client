<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Exception;

/**
 * Everything this package throws on purpose for a failed call has this class as its ancestor.
 */
class MentionApiException extends \RuntimeException
{
    /** @var string|null */
    private $requestId;

    public function __construct(string $message, ?string $requestId = null, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->requestId = $requestId;
    }

    /**
     * The id mention-api gave the call, when an answer came back at all; quote it when you ask about the call.
     */
    public function getRequestId(): ?string
    {
        return $this->requestId;
    }
}
