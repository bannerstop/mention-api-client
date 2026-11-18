<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Result;

/**
 * The rows a generated Query method returns, as objects of the Row class of the Query, with everything the generic
 * QueryResult knows about the call.
 *
 * @template T of object
 *
 * @implements \IteratorAggregate<int, T>
 */
final class TypedRows implements \Countable, \IteratorAggregate
{
    private QueryResult $result;
    /** @var list<T> */
    private array $rows;

    /**
     * @param list<T> $rows
     */
    public function __construct(QueryResult $result, array $rows)
    {
        $this->result = $result;
        $this->rows = $rows;
    }

    /**
     * @return list<T>
     */
    public function getRows(): array
    {
        return $this->rows;
    }

    /**
     * The answer as the generic client gave it, with the rows as arrays.
     */
    public function getResult(): QueryResult
    {
        return $this->result;
    }

    public function getQuery(): string
    {
        return $this->result->getQuery();
    }

    public function getVersion(): int
    {
        return $this->result->getVersion();
    }

    public function getDurationMs(): int
    {
        return $this->result->getDurationMs();
    }

    public function getRequestId(): ?string
    {
        return $this->result->getRequestId();
    }

    public function isDeprecated(): bool
    {
        return $this->result->isDeprecated();
    }

    public function getDeprecatedSince(): ?\DateTimeImmutable
    {
        return $this->result->getDeprecatedSince();
    }

    public function count(): int
    {
        return \count($this->rows);
    }

    /**
     * @return \ArrayIterator<int, T>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->rows);
    }
}
