<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Exception;

/**
 * An error answer of mention-api (RFC 9457 problem details). Each problem code has a subclass.
 */
abstract class ProblemException extends MentionApiException
{
    private int $status;
    private string $problemCode;
    private string $title;
    private string $detail;
    /** @var array<string, string> */
    private array $errors;
    private ?int $retryAfter;

    /**
     * @param array<string, string> $errors     what is wrong, per Parameter
     * @param int|null              $retryAfter seconds the server asked to wait before another attempt
     */
    final public function __construct(int $status, string $problemCode, string $title, string $detail, ?string $requestId = null, array $errors = [], ?int $retryAfter = null)
    {
        parent::__construct($title . ': ' . $detail . (null === $requestId ? '' : ' (request ' . $requestId . ')'), $requestId, $status);
        $this->status = $status;
        $this->problemCode = $problemCode;
        $this->title = $title;
        $this->detail = $detail;
        $this->errors = $errors;
        $this->retryAfter = $retryAfter;
    }

    /**
     * The HTTP status of the answer.
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * The machine-readable code, the last part of the problem type: "invalid-parameters", "unknown-query", ...
     */
    public function getProblemCode(): string
    {
        return $this->problemCode;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDetail(): string
    {
        return $this->detail;
    }

    /**
     * What is wrong, per Parameter name (answers with status 422); empty otherwise.
     *
     * @return array<string, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Seconds the server asked to wait (header Retry-After), mostly with status 429 and 503.
     */
    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
