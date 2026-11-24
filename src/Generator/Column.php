<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Generator;

/**
 * A column of the rows a Query returns.
 *
 * @internal
 */
final class Column
{
    /** @var string */
    private $name;
    /** @var string */
    private $type;
    /** @var string */
    private $description;

    public function __construct(string $name, string $type, string $description)
    {
        $this->name = $name;
        $this->type = $type;
        $this->description = $description;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * The database type as the document gives it, e.g. "int" or "nvarchar"; empty when it does not say.
     */
    public function getType(): string
    {
        return $this->type;
    }

    public function getDescription(): string
    {
        return $this->description;
    }
}
