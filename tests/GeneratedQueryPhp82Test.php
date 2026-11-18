<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Tests;

use Bannerstop\MentionApi\Generator\PhpTarget;

final class GeneratedQueryPhp82Test extends GeneratedQueryTestCase
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

    public function testGeneratedClassesAreReadonly(): void
    {
        $code = new GeneratedCode(GeneratedCode::fixtureDocument(), $this->target());
        $class = $code->class('Query\\ListWidgetsRow');
        $row = $class::fromArray(['widgetId' => 2]);

        $this->expectException(\Error::class);

        $row->widgetId = 3;
    }

    public function testEveryGeneratedClassIsReadonly(): void
    {
        $code = new GeneratedCode(GeneratedCode::fixtureDocument(), $this->target());

        $readonly = array_filter($code->files(), static function (string $source): bool {
            return false !== strpos($source, 'final readonly class');
        });

        self::assertCount(\count($code->files()), $readonly);
    }
}
