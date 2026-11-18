<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Result;

/**
 * What to send to a Query and what comes back.
 */
final class QueryDescription extends Result
{
    private string $name;
    private int $version;
    private string $description;
    /** @var list<array{name: string, type: string}> */
    private array $parameters;
    /** @var list<array{name: string, type: string, description?: string}> */
    private array $columns;
    /** @var list<string> */
    private array $databases;
    private int $timeoutSeconds;
    private int $rowLimit;

    /**
     * @param list<array{name: string, type: string}>                      $parameters
     * @param list<array{name: string, type: string, description?: string}> $columns
     * @param list<string>                                                 $databases
     */
    public function __construct(string $name, int $version, string $description, array $parameters, array $columns, array $databases, int $timeoutSeconds, int $rowLimit, ?string $requestId, ?string $deprecation = null)
    {
        parent::__construct($requestId, $deprecation);
        $this->name = $name;
        $this->version = $version;
        $this->description = $description;
        $this->parameters = $parameters;
        $this->columns = $columns;
        $this->databases = $databases;
        $this->timeoutSeconds = $timeoutSeconds;
        $this->rowLimit = $rowLimit;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * The Parameters the Query takes, all of them required, with their types (int, string, date, bool, int_list, string_list).
     *
     * @return list<array{name: string, type: string}>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * The columns of every row.
     *
     * @return list<array{name: string, type: string, description?: string}>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * The Mention databases the Query can run on.
     *
     * @return list<string>
     */
    public function getDatabases(): array
    {
        return $this->databases;
    }

    public function getTimeoutSeconds(): int
    {
        return $this->timeoutSeconds;
    }

    /**
     * More rows than this are refused with ResultTooLargeException, never cut off silently.
     */
    public function getRowLimit(): int
    {
        return $this->rowLimit;
    }
}
