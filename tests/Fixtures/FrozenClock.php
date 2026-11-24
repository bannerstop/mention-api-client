<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Tests\Fixtures;

use Psr\Clock\ClockInterface;

final class FrozenClock implements ClockInterface
{
    /** @var \DateTimeImmutable */
    private $now;

    public function __construct(string $now = '2026-11-18 12:00:00 UTC')
    {
        $this->now = new \DateTimeImmutable($now);
    }

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(int $seconds): void
    {
        $this->now = $this->now->modify(sprintf('+%d seconds', $seconds));
    }
}
