<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Result;

/**
 * The Queries your Consumer may call; each entry has the keys name, version and description.
 *
 * @implements \IteratorAggregate<int, array<string, mixed>>
 */
final class QueryList extends Result implements \Countable, \IteratorAggregate
{
    /** @var list<array<string, mixed>> */
    private array $queries;

    /**
     * @param list<array<string, mixed>> $queries
     */
    public function __construct(array $queries, ?string $requestId)
    {
        parent::__construct($requestId);
        $this->queries = $queries;
    }

    /**
     * @return list<array<string, mixed>>
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

    /**
     * @return \ArrayIterator<int, array<string, mixed>>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->queries);
    }
}
