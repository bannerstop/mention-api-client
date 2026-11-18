<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Tests;

use Bannerstop\MentionApi\Generator\PhpTarget;

final class GeneratedClientPhp82Test extends GeneratedClientTestCase
{
    protected function setUp(): void
    {
        if (\PHP_VERSION_ID < 80200) {
            self::markTestSkipped('Readonly classes need PHP 8.2.');
        }
        parent::setUp();
    }

    protected function target(): PhpTarget
    {
        return PhpTarget::php82();
    }
}
