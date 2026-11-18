<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Result;

/**
 * The Routines your Consumer may call, each with its Parameters (name, type and, for strings, an optional format and maximum length).
 *
 * @implements \IteratorAggregate<int, array{name: string, version: int, description: string, parameters: list<array<string, mixed>>}>
 */
final class RoutineList extends Result implements \Countable, \IteratorAggregate
{
    /** @var list<array{name: string, version: int, description: string, parameters: list<array<string, mixed>>}> */
    private array $routines;

    /**
     * @param list<array{name: string, version: int, description: string, parameters: list<array<string, mixed>>}> $routines
     */
    public function __construct(array $routines, ?string $requestId)
    {
        parent::__construct($requestId);
        $this->routines = $routines;
    }

    /**
     * @return list<array{name: string, version: int, description: string, parameters: list<array<string, mixed>>}>
     */
    public function getRoutines(): array
    {
        return $this->routines;
    }

    /**
     * @return list<string>
     */
    public function getNames(): array
    {
        return array_column($this->routines, 'name');
    }

    public function count(): int
    {
        return \count($this->routines);
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->routines);
    }
}
