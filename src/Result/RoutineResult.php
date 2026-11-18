<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Result;

/**
 * What a Routine did.
 */
final class RoutineResult extends Result
{
    private string $routine;
    private string $action;
    private int $rowsWritten;
    private int $durationMs;

    public function __construct(string $routine, string $action, int $rowsWritten, int $durationMs, ?string $requestId)
    {
        parent::__construct($requestId);
        $this->routine = $routine;
        $this->action = $action;
        $this->rowsWritten = $rowsWritten;
        $this->durationMs = $durationMs;
    }

    public function getRoutine(): string
    {
        return $this->routine;
    }

    /**
     * What happened to the item: "inserted", "updated" or "unchanged".
     */
    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * How many rows the Routine wrote to Mention; 0 when the item was unchanged.
     */
    public function getRowsWritten(): int
    {
        return $this->rowsWritten;
    }

    public function getDurationMs(): int
    {
        return $this->durationMs;
    }
}
