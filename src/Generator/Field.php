<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Generator;

/**
 * A property of a generated class: what the code needs to declare it.
 *
 * @internal
 */
final class Field
{
    private string $name;
    private string $declaredType;
    private string $docType;
    private string $description;
    private bool $optional;

    /**
     * @param string $declaredType the PHP type declaration, empty for none
     * @param string $docType      the type for the docblock
     * @param bool   $optional     whether it defaults to null
     */
    public function __construct(string $name, string $declaredType, string $docType, string $description = '', bool $optional = false)
    {
        $this->name = $name;
        $this->declaredType = $declaredType;
        $this->docType = $docType;
        $this->description = $description;
        $this->optional = $optional;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDeclaredType(): string
    {
        return $this->declaredType;
    }

    public function getDocType(): string
    {
        return $this->docType;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function isOptional(): bool
    {
        return $this->optional;
    }

    /**
     * Whether the docblock adds something the declaration does not say.
     */
    public function needsDocType(): bool
    {
        return '' === $this->declaredType || 'array' === $this->declaredType || '?array' === $this->declaredType;
    }

    /**
     * The type with its name, as in a constructor signature: "?int $id" or "$value".
     */
    public function signature(): string
    {
        return ('' === $this->declaredType ? '' : $this->declaredType . ' ') . '$' . $this->name . ($this->optional ? ' = null' : '');
    }
}
