<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Http;

use Bannerstop\MentionApi\Exception\UnexpectedResponseException;

/**
 * A successful answer, decoded.
 *
 * @internal
 */
final class ApiResponse
{
    /** @var array<string, mixed> */
    private array $document;
    private ?string $requestId;

    /**
     * @param array<string, mixed> $document
     */
    public function __construct(array $document, ?string $requestId)
    {
        $this->document = $document;
        $this->requestId = $requestId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDocument(): array
    {
        return $this->document;
    }

    /**
     * The "data" member of the answer.
     *
     * @return array<array-key, mixed>
     *
     * @throws UnexpectedResponseException when the answer has none
     */
    public function getData(): array
    {
        if (!isset($this->document['data']) || !\is_array($this->document['data'])) {
            throw new UnexpectedResponseException('The answer has no "data" member.', 200, $this->requestId);
        }

        return $this->document['data'];
    }

    /**
     * @return array<string, mixed>
     */
    public function getMeta(): array
    {
        return isset($this->document['meta']) && \is_array($this->document['meta']) ? $this->document['meta'] : [];
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }
}
