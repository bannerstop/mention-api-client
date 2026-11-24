<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Result;

/**
 * The rows a Query returned, each as an associative array of column => value.
 *
 * @implements \IteratorAggregate<int, array<string, mixed>>
 */
final class QueryResult extends Result implements \Countable, \IteratorAggregate
{
    /** @var list<array<string, mixed>> */
    private $rows;

    /** @var string */
    private $query;
    /** @var int */
    private $version;
    /** @var int */
    private $durationMs;

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function __construct(array $rows, string $query, int $version, int $durationMs, ?string $requestId, ?string $deprecation = null)
    {
        parent::__construct($requestId, $deprecation);
        $this->rows = $rows;
        $this->query = $query;
        $this->version = $version;
        $this->durationMs = $durationMs;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    /**
     * The version of the Query that answered.
     */
    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * How long mention-api needed to run the Query.
     */
    public function getDurationMs(): int
    {
        return $this->durationMs;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getRows(): array
    {
        return $this->rows;
    }

    public function count(): int
    {
        return \count($this->rows);
    }

    /**
     * @return \ArrayIterator<int, array<string, mixed>>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->rows);
    }
}
