<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Tests;

use Bannerstop\MentionApi\Generator\GeneratorException;
use Bannerstop\MentionApi\Generator\PhpTarget;
use PHPUnit\Framework\TestCase;

final class PhpTargetTest extends TestCase
{
    public function testTheLowestPhpTheAppSupportsDecidesTheTarget(): void
    {
        $expected = [
            '^7.2.5 || ^8.0' => '7.2',
            '^7.2' => '7.2',
            '>=7.2' => '7.2',
            '7.2.*' => '7.2',
            '^7.3' => '7.2',
            '~7.3.0' => '7.2',
            '>=7.1' => '7.2',
            '>=7.3 <8.0 || ^8.2' => '7.2',
            '^7.4 || ^8.0' => '7.4',
            '^8.1' => '7.4',
            '^8' => '7.4',
            '8.1.*' => '7.4',
            '*' => '7.4',
            '^8.2' => '8.2',
            '^8.2.0' => '8.2',
            '~8.2.0' => '8.2',
            '~8.3' => '8.2',
            '>=8.2' => '8.2',
            '>=8.2.0 <8.5' => '8.2',
            '>=8.2, <8.5' => '8.2',
            '>8.1' => '7.4',
            '8.2.*' => '8.2',
            '8.4.1' => '8.2',
            '8.2 - 8.4' => '8.2',
            '^8.2 || ^8.3 || ^8.4' => '8.2',
            '^8.3 | ^8.4' => '8.2',
            '^9.0' => '8.2',
            '^8.2@dev' => '8.2',
            '^7.4 || ^8.2' => '7.4',
            '^7.4 || >=8.2 <8.5' => '7.4',
            '>=7.4 <8.0 || ^8.2' => '7.4',
        ];

        foreach ($expected as $requirement => $target) {
            self::assertSame($target, (string) PhpTarget::fromRequirement((string) $requirement), 'PHP ' . $requirement);
        }
    }

    public function testARequirementThatIsNoVersionConstraintIsRefused(): void
    {
        $this->expectException(GeneratorException::class);

        PhpTarget::fromRequirement('whatever');
    }

    public function testATargetCanBeNamed(): void
    {
        self::assertTrue(PhpTarget::fromString('8.2')->usesReadonlyClasses());
        self::assertFalse(PhpTarget::fromString('7.4')->usesReadonlyClasses());
        self::assertFalse(PhpTarget::fromString('7.2')->usesReadonlyClasses());
        self::assertSame('7.2', (string) PhpTarget::fromString('7.2'));
    }

    public function testAnUnknownTargetIsRefused(): void
    {
        $this->expectException(GeneratorException::class);

        PhpTarget::fromString('8.3');
    }
}
