<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Generator;

/**
 * The PHP the generated code is written for: plain classes that run from PHP 7.4, or readonly classes from PHP 8.2.
 */
final class PhpTarget
{
    private bool $readonly;

    private function __construct(bool $readonly)
    {
        $this->readonly = $readonly;
    }

    public static function php74(): self
    {
        return new self(false);
    }

    public static function php82(): self
    {
        return new self(true);
    }

    /**
     * @param string $version "7.4" or "8.2"
     */
    public static function fromString(string $version): self
    {
        if ('7.4' === $version) {
            return self::php74();
        }
        if ('8.2' === $version) {
            return self::php82();
        }

        throw new GeneratorException(sprintf('"%s" is not a PHP target; use 7.4 or 8.2.', $version));
    }

    public function usesReadonlyClasses(): bool
    {
        return $this->readonly;
    }

    public function __toString(): string
    {
        return $this->readonly ? '8.2' : '7.4';
    }
}
