<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Result;

/**
 * What every successful call has in common: the request id of the call, and the deprecation notice of a Query.
 */
abstract class Result
{
    /** @var string|null */
    private $requestId;
    /** @var string|null */
    private $deprecation;

    public function __construct(?string $requestId, ?string $deprecation = null)
    {
        $this->requestId = $requestId;
        $this->deprecation = $deprecation;
    }

    /**
     * The id mention-api gave this call (header X-Request-Id); quote it when you ask about the call.
     */
    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    /**
     * Whether the Query is deprecated: it still works, but you should move to its replacement before it is retired.
     * Only answers about a Query can say so.
     */
    public function isDeprecated(): bool
    {
        return null !== $this->deprecation;
    }

    /**
     * When the Query was deprecated (header Deprecation, RFC 9745), if the server said so.
     */
    public function getDeprecatedSince(): ?\DateTimeImmutable
    {
        if (null === $this->deprecation || 1 !== preg_match('/^@(\d+)$/', $this->deprecation, $match)) {
            return null;
        }

        return (new \DateTimeImmutable('@' . $match[1]))->setTimezone(new \DateTimeZone('UTC'));
    }
}
