<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Tests;

use Bannerstop\MentionApi\Generator\PhpTarget;

final class GeneratedQueryPhp74Test extends GeneratedQueryTestCase
{
    protected function target(): PhpTarget
    {
        return PhpTarget::php74();
    }

    public function testNoGeneratedFileUsesSyntaxOfNewerPhp(): void
    {
        $code = new GeneratedCode(GeneratedCode::fixtureDocument(), $this->target());

        foreach ($code->files() as $path => $source) {
            self::assertSame(0, preg_match('/\\breadonly\\b(?! \*\/)|\\bmixed \\$|: mixed\\b|\\bmatch \\(|\\benum \\w|#\\[/', $source), $path . ' uses syntax newer than PHP 7.4');
        }
    }
}
