<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Generator;

/**
 * A Parameter of a Query or a Routine.
 *
 * @internal
 */
final class Parameter
{
    private string $name;
    private string $type;
    private bool $required;

    public function __construct(string $name, string $type, bool $required)
    {
        $this->name = $name;
        $this->type = $type;
        $this->required = $required;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * One of int, string, bool, date, int_list, string_list.
     */
    public function getType(): string
    {
        return $this->type;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }
}
