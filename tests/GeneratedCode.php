<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Tests;

use Bannerstop\MentionApi\Generator\PhpTarget;
use Bannerstop\MentionApi\Generator\WrapperGenerator;

/**
 * Generates the wrappers for a document and loads them, each time into a namespace of its own so that several
 * generated sets can live in one process.
 */
final class GeneratedCode
{
    /** @var int */
    private static $counter = 0;

    /** @var string */
    private $namespace;

    /** @var array<string, string> */
    private $files;

    /**
     * @param array<string, mixed> $document
     */
    public function __construct(array $document, PhpTarget $target)
    {
        $this->namespace = 'Bannerstop\\MentionApi\\Tests\\Loaded\\N' . (++self::$counter);
        $this->files = (new WrapperGenerator())->generate($document, $this->namespace, $target);
        $directory = sys_get_temp_dir() . '/mention-api-client-' . getmypid() . '-' . self::$counter;
        foreach ($this->files as $path => $code) {
            if (!is_dir(\dirname($directory . '/' . $path))) {
                mkdir(\dirname($directory . '/' . $path), 0777, true);
            }
            file_put_contents($directory . '/' . $path, $code);
            require_once $directory . '/' . $path;
            unlink($directory . '/' . $path);
        }
        foreach (['Query', 'Routine'] as $sub) {
            @rmdir($directory . '/' . $sub);
        }
        @rmdir($directory);
    }

    public static function fixtureDocument(): array
    {
        return json_decode((string) file_get_contents(__DIR__ . '/Fixtures/openapi.json'), true);
    }

    /**
     * @return class-string
     */
    public function class(string $relative): string
    {
        return $this->namespace . '\\' . $relative;
    }

    /**
     * @return array<string, string>
     */
    public function files(): array
    {
        return $this->files;
    }
}
