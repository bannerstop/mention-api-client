<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Generator;

/**
 * The PHP the generated code is written for: plain classes that run from PHP 7.4, or readonly classes from PHP 8.2.
 *
 * @internal
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

    /**
     * The target for an app that requires the given PHP in its composer.json: readonly classes only when even the
     * lowest PHP the constraint allows is 8.2 or higher.
     *
     * @throws GeneratorException when the constraint cannot be read
     */
    public static function fromRequirement(string $constraint): self
    {
        $lowest = null;
        foreach (preg_split('/\s*\|\|?\s*/', trim($constraint)) ?: [] as $alternative) {
            $bound = self::lowestOf($alternative, $constraint);
            $lowest = null === $lowest ? $bound : min($lowest, $bound);
        }

        return null !== $lowest && $lowest >= 802 ? self::php82() : self::php74();
    }

    /**
     * The lowest version of one alternative (clauses joined by spaces or commas), as major * 100 + minor.
     */
    private static function lowestOf(string $alternative, string $constraint): int
    {
        $alternative = (string) preg_replace('/@\w+/', '', $alternative);
        $alternative = (string) preg_replace('/\s+-\s+\S+/', '', $alternative); // "8.2 - 8.4": the upper end sets no lower bound
        $lowest = 0;
        $clauses = preg_split('/[\s,]+/', trim($alternative), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ([] === $clauses) {
            throw new GeneratorException(sprintf('Cannot read the PHP requirement "%s"; pass the target with --php.', $constraint));
        }
        foreach ($clauses as $clause) {
            if (1 === preg_match('/^(<|<=|!=|<>)/', $clause) || '*' === $clause) {
                continue;
            }
            if (1 !== preg_match('/^(?:\^|~|>=|>|=|v)?(\d+)(?:\.(\d+|\*|x))?/', $clause, $match)) {
                throw new GeneratorException(sprintf('Cannot read the PHP requirement "%s"; pass the target with --php.', $constraint));
            }
            $minor = isset($match[2]) && ctype_digit($match[2]) ? (int) $match[2] : 0;
            $lowest = max($lowest, (int) $match[1] * 100 + $minor);
        }

        return $lowest;
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
