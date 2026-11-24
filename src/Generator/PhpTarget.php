<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Generator;

/**
 * The PHP the generated code is written for: plain classes without typed properties that run from PHP 7.2, plain
 * classes with typed properties from PHP 7.4, or readonly classes from PHP 8.2.
 *
 * @internal
 */
final class PhpTarget
{
    /** @var string */
    private $version;

    private function __construct(string $version)
    {
        $this->version = $version;
    }

    public static function php72(): self
    {
        return new self('7.2');
    }

    public static function php74(): self
    {
        return new self('7.4');
    }

    public static function php82(): self
    {
        return new self('8.2');
    }

    /**
     * @param string $version "7.2", "7.4" or "8.2"
     */
    public static function fromString(string $version): self
    {
        if ('7.2' === $version) {
            return self::php72();
        }
        if ('7.4' === $version) {
            return self::php74();
        }
        if ('8.2' === $version) {
            return self::php82();
        }

        throw new GeneratorException(sprintf('"%s" is not a PHP target; use 7.2, 7.4 or 8.2.', $version));
    }

    /**
     * The target for an app that requires the given PHP in its composer.json: no typed properties when the lowest PHP
     * the constraint names is below 7.4, readonly classes only when even that is 8.2 or higher.
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

        if (null === $lowest || 0 === $lowest) { // no lower bound at all ("*"): nothing says the app is older than 7.4
            return self::php74();
        }
        if ($lowest >= 802) {
            return self::php82();
        }

        return $lowest < 704 ? self::php72() : self::php74();
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
        return '8.2' === $this->version;
    }

    public function usesTypedProperties(): bool
    {
        return '7.2' !== $this->version;
    }

    /**
     * A comma after the last argument of a call is a parse error before PHP 7.3.
     */
    public function allowsTrailingCommaInCalls(): bool
    {
        return '7.2' !== $this->version;
    }

    public function __toString(): string
    {
        return $this->version;
    }
}
