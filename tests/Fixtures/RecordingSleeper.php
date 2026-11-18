<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Tests\Fixtures;

use Bannerstop\MentionApi\Http\Sleeper;

final class RecordingSleeper implements Sleeper
{
    /** @var list<float> */
    public array $sleeps = [];

    public function sleep(float $seconds): void
    {
        $this->sleeps[] = $seconds;
    }
}
