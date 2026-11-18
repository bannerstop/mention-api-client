<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Http;

/**
 * Waits between two attempts of a retried call. Replace it in tests so that they do not sleep.
 */
interface Sleeper
{
    public function sleep(float $seconds): void;
}
