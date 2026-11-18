<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Tests;

use Bannerstop\MentionApi\Generator\PhpTarget;

final class GeneratedClientPhp74Test extends GeneratedClientTestCase
{
    protected function target(): PhpTarget
    {
        return PhpTarget::php74();
    }
}
