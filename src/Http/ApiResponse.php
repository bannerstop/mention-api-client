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
    private $document;
    /** @var string|null */
    private $requestId;
    /** @var string|null */
    private $deprecation;

    /**
     * @param array<string, mixed> $document
     */
    public function __construct(array $document, ?string $requestId, ?string $deprecation = null)
    {
        $this->document = $document;
        $this->requestId = $requestId;
        $this->deprecation = $deprecation;
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
     * The "data" member of the answer, which must be a list of objects.
     *
     * @return list<array<string, mixed>>
     *
     * @throws UnexpectedResponseException when it is not
     */
    public function getItems(): array
    {
        $items = [];
        foreach ($this->getData() as $item) {
            if (!\is_array($item)) {
                throw new UnexpectedResponseException('The "data" member of the answer is not a list of objects.', 200, $this->requestId);
            }
            $items[] = $item;
        }

        return $items;
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

    /**
     * The value of the Deprecation header, when the Query is deprecated.
     */
    public function getDeprecation(): ?string
    {
        return $this->deprecation;
    }
}
