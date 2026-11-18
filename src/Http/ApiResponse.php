<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Http;

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

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }
}
