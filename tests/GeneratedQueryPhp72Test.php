<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Tests;

use Bannerstop\MentionApi\Generator\PhpTarget;

final class GeneratedQueryPhp72Test extends GeneratedQueryTestCase
{
    protected function target(): PhpTarget
    {
        return PhpTarget::php72();
    }

    public function testNoGeneratedFileUsesSyntaxOfNewerPhp(): void
    {
        $code = new GeneratedCode(GeneratedCode::fixtureDocument(), $this->target());

        foreach ($code->files() as $path => $source) {
            self::assertSame(0, preg_match('/(?<!@)\breadonly\b|(?:public|private|protected) \??[A-Za-z]+ \$|\bfn \(|\bmatch \(|\benum \w|#\[/', $source), $path . ' uses syntax newer than PHP 7.2');
        }
    }
}
