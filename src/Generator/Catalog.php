<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Generator;

/**
 * The Queries and Routines of an OpenAPI document, each list sorted by name.
 *
 * @internal
 */
final class Catalog
{
    private const IDENTIFIER = '/^[A-Za-z_][A-Za-z0-9_]*$/';
    private const OPERATION_PATH = '#^/v2/(queries|routines)/([A-Za-z][A-Za-z0-9]{0,127})$#';

    /** @var list<Operation> */
    private array $queries;
    /** @var list<Operation> */
    private array $routines;

    /**
     * @param list<Operation> $queries
     * @param list<Operation> $routines
     */
    private function __construct(array $queries, array $routines)
    {
        $this->queries = $queries;
        $this->routines = $routines;
    }

    /**
     * @param array<string, mixed> $document the decoded /v2/openapi.json
     *
     * @throws GeneratorException when the document is not what mention-api gives
     */
    public static function fromDocument(array $document): self
    {
        if (!isset($document['openapi']) || !\is_string($document['openapi']) || !isset($document['paths']) || !\is_array($document['paths'])) {
            throw new GeneratorException('This is not an OpenAPI document: "openapi" or "paths" is missing.');
        }

        $queries = [];
        $routines = [];
        foreach ($document['paths'] as $path => $item) {
            if (1 !== preg_match(self::OPERATION_PATH, (string) $path, $match) || !\is_array($item) || !isset($item['post']) || !\is_array($item['post'])) {
                continue;
            }
            $name = $match[2];
            if ('queries' === $match[1]) {
                $queries[$name] = self::operation($name, $item['post'], true);
            } else {
                $routines[$name] = self::operation($name, $item['post'], false);
            }
        }

        return new self(self::sorted($queries, 'Query'), self::sorted($routines, 'Routine'));
    }

    /**
     * @return list<Operation>
     */
    public function getQueries(): array
    {
        return $this->queries;
    }

    /**
     * @return list<Operation>
     */
    public function getRoutines(): array
    {
        return $this->routines;
    }

    /**
     * @param array<string, Operation> $operations
     *
     * @return list<Operation>
     */
    private static function sorted(array $operations, string $kind): array
    {
        ksort($operations, SORT_STRING);
        $seen = [];
        foreach (array_keys($operations) as $name) {
            $key = strtolower((string) $name);
            if (isset($seen[$key])) {
                throw new GeneratorException(sprintf('The %ss "%s" and "%s" differ only in case, so their classes would collide.', $kind, $seen[$key], $name));
            }
            $seen[$key] = (string) $name;
        }

        return array_values($operations);
    }

    /**
     * @param array<string, mixed> $post
     */
    private static function operation(string $name, array $post, bool $withColumns): Operation
    {
        $summary = $post['summary'] ?? '';
        $schema = $post['requestBody']['content']['application/json']['schema']['properties']['parameters'] ?? null;
        if (!\is_array($schema)) {
            throw new GeneratorException(sprintf('"%s" has no Parameter schema in the document.', $name));
        }

        return new Operation(
            $name,
            \is_string($summary) ? $summary : '',
            self::parameters($name, $schema),
            $withColumns ? self::columns($name, $post) : []
        );
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return list<Parameter>
     */
    private static function parameters(string $operation, array $schema): array
    {
        $properties = isset($schema['properties']) && \is_array($schema['properties']) ? $schema['properties'] : [];
        $required = isset($schema['required']) && \is_array($schema['required']) ? $schema['required'] : [];
        $requiredParameters = [];
        $optionalParameters = [];
        foreach ($properties as $name => $property) {
            $name = (string) $name;
            self::assertIdentifier($name, sprintf('The Parameter "%s" of "%s"', $name, $operation));
            if (!\is_array($property)) {
                throw new GeneratorException(sprintf('The Parameter "%s" of "%s" has no schema.', $name, $operation));
            }
            $isRequired = \in_array($name, $required, true);
            $parameter = new Parameter($name, self::parameterType($operation, $name, $property), $isRequired);
            if ($isRequired) {
                $requiredParameters[] = $parameter;
            } else {
                $optionalParameters[] = $parameter;
            }
        }

        return array_merge($requiredParameters, $optionalParameters);
    }

    /**
     * @param array<string, mixed> $property
     */
    private static function parameterType(string $operation, string $name, array $property): string
    {
        $type = $property['type'] ?? null;
        $item = isset($property['items']) && \is_array($property['items']) ? ($property['items']['type'] ?? null) : null;
        if ('integer' === $type) {
            return 'int';
        }
        if ('boolean' === $type) {
            return 'bool';
        }
        if ('string' === $type) {
            return 'date' === ($property['format'] ?? null) ? 'date' : 'string';
        }
        if ('array' === $type && 'integer' === $item) {
            return 'int_list';
        }
        if ('array' === $type && 'string' === $item) {
            return 'string_list';
        }

        throw new GeneratorException(sprintf('The Parameter "%s" of "%s" has a type this generator does not know; update the package.', $name, $operation));
    }

    /**
     * @param array<string, mixed> $post
     *
     * @return list<Column>
     */
    private static function columns(string $operation, array $post): array
    {
        $row = $post['responses']['200']['content']['application/json']['schema']['properties']['data']['items'] ?? [];
        $properties = \is_array($row) && isset($row['properties']) && \is_array($row['properties']) ? $row['properties'] : [];
        $columns = [];
        foreach ($properties as $name => $property) {
            $name = (string) $name;
            self::assertIdentifier($name, sprintf('The column "%s" of "%s"', $name, $operation));
            $property = \is_array($property) ? $property : [];
            $type = $property['x-type'] ?? '';
            $description = $property['description'] ?? '';
            $columns[] = new Column($name, \is_string($type) ? strtolower($type) : '', \is_string($description) ? $description : '');
        }

        return $columns;
    }

    private static function assertIdentifier(string $name, string $what): void
    {
        if (1 !== preg_match(self::IDENTIFIER, $name) || 'this' === $name) {
            throw new GeneratorException($what . ' cannot be a PHP property name: use letters, digits and underscores, starting with a letter or underscore.');
        }
    }
}
