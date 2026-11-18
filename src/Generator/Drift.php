<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Generator;

/**
 * How the files on disk differ from what the generator would write.
 */
final class Drift
{
    /** @var list<string> */
    private array $missing;
    /** @var list<string> */
    private array $changed;
    /** @var list<string> */
    private array $stale;

    /**
     * @param list<string> $missing files the generator would write that do not exist
     * @param list<string> $changed files that exist with other content
     * @param list<string> $stale   generated files the generator would no longer write
     */
    public function __construct(array $missing, array $changed, array $stale)
    {
        $this->missing = $missing;
        $this->changed = $changed;
        $this->stale = $stale;
    }

    public function isEmpty(): bool
    {
        return [] === $this->missing && [] === $this->changed && [] === $this->stale;
    }

    /**
     * @return list<string>
     */
    public function getMissing(): array
    {
        return $this->missing;
    }

    /**
     * @return list<string>
     */
    public function getChanged(): array
    {
        return $this->changed;
    }

    /**
     * @return list<string>
     */
    public function getStale(): array
    {
        return $this->stale;
    }
}
