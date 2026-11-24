<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Tests;

use Bannerstop\MentionApi\Generator\PhpTarget;

final class GeneratedQueryPhp74Test extends GeneratedQueryTestCase
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

    public function testNoGeneratedFileUsesSyntaxOfNewerPhp(): void
    {
        $code = new GeneratedCode(GeneratedCode::fixtureDocument(), $this->target());

        foreach ($code->files() as $path => $source) {
            self::assertSame(0, preg_match('/(?<!@)\breadonly\b|(?:public|\(|,) mixed \$|: mixed\b|\bmatch \(|\benum \w|#\[/', $source), $path . ' uses syntax newer than PHP 7.4');
        }
    }
}
