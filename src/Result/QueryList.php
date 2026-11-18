<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Result;

/**
 * The Queries your Consumer may call.
 *
 * @implements \IteratorAggregate<int, array{name: string, version: int, description: string}>
 */
final class QueryList extends Result implements \Countable, \IteratorAggregate
{
    /** @var list<array{name: string, version: int, description: string}> */
    private array $queries;

    /**
     * @param list<array{name: string, version: int, description: string}> $queries
     */
    public function __construct(array $queries, ?string $requestId)
    {
        parent::__construct($requestId);
        $this->queries = $queries;
    }

    /**
     * @return list<array{name: string, version: int, description: string}>
     */
    public function getQueries(): array
    {
        return $this->queries;
    }

    /**
     * @return list<string>
     */
    public function getNames(): array
    {
        return array_column($this->queries, 'name');
    }

    public function count(): int
    {
        return \count($this->queries);
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->queries);
    }
}
