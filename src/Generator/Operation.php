<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Generator;

/**
 * A Query or a Routine of the OpenAPI document.
 *
 * @internal
 */
final class Operation
{
    private string $name;
    private string $summary;
    /** @var list<Parameter> */
    private array $parameters;
    /** @var list<Column> */
    private array $columns;

    /**
     * @param list<Parameter> $parameters
     * @param list<Column>    $columns    what a Query returns; empty for a Routine
     */
    public function __construct(string $name, string $summary, array $parameters, array $columns)
    {
        $this->name = $name;
        $this->summary = $summary;
        $this->parameters = $parameters;
        $this->columns = $columns;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSummary(): string
    {
        return $this->summary;
    }

    /**
     * @return list<Parameter>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * @return list<Column>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }
}
