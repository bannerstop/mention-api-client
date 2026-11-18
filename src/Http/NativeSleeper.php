<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Http;

final class NativeSleeper implements Sleeper
{
    public function sleep(float $seconds): void
    {
        if ($seconds > 0) {
            usleep((int) round($seconds * 1000000));
        }
    }
}
