<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Tests;

use Bannerstop\MentionApi\Generator\PhpTarget;

final class GeneratedClientPhp74Test extends GeneratedClientTestCase
{
    protected function setUp(): void
    {
        if (\PHP_VERSION_ID < 70400) {
            self::markTestSkipped('Typed properties need PHP 7.4.');
        }
        parent::setUp();
    }

    protected function target(): PhpTarget
    {
        return PhpTarget::php74();
    }
}
