<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Generator\Console;

use Bannerstop\MentionApi\Exception\MentionApiException;
use Bannerstop\MentionApi\Generator\GeneratorException;
use Bannerstop\MentionApi\Generator\OutputDirectory;
use Bannerstop\MentionApi\Generator\PhpTarget;
use Bannerstop\MentionApi\Generator\WrapperGenerator;
use Bannerstop\MentionApi\MentionApiClient;

/**
 * The mention-api-client command line: generate typed wrappers, or check that the committed ones are current.
 *
 * @internal
 */
final class Application
{
    private const USAGE = <<<'TEXT'
Usage:
  mention-api-client generate --namespace=<namespace> --dir=<directory> [options]

Reads the OpenAPI document of your Consumer and writes typed wrapper classes: per Query a Parameters class, a Row
class and a method, the same for Routines, plus Queries.php and Routines.php. Commit the output.

Options:
  --namespace=<ns>      the PHP namespace of the generated classes, e.g. App\Mention
  --dir=<dir>           where to write them, e.g. src/Mention
  --check               write nothing; exit 1 when the files differ from what would be generated
  --source=<file>       read the OpenAPI document from this file instead of fetching it
  --client=<file>       a PHP file that returns your configured MentionApiClient, used to fetch the document
  --php=<7.4|8.2>       the PHP the code is written for; by default decided by "require.php" of composer.json
  --composer=<file>     the composer.json to read (default: composer.json in the current directory)
  --help                show this text

Without --source and --client the document is fetched with the environment variables MENTION_API_URL and either
MENTION_API_KEY or MENTION_API_TOKEN_URL, MENTION_API_CLIENT_ID and MENTION_API_CLIENT_SECRET (MENTION_API_SCOPE
is optional), using Guzzle 7 or Symfony HttpClient.

Paths are relative to the current directory. Exit status: 0 success, 1 the files differ (--check), 2 error.

TEXT;

    private const VALUE_OPTIONS = ['namespace', 'dir', 'source', 'client', 'php', 'composer'];

    /** @var callable(string): void */
    private $stdout;
    /** @var callable(string): void */
    private $stderr;

    /**
     * @param callable(string): void $stdout
     * @param callable(string): void $stderr
     */
    public function __construct(callable $stdout, callable $stderr)
    {
        $this->stdout = $stdout;
        $this->stderr = $stderr;
    }

    /**
     * @param list<string>          $args the arguments after the script name
     * @param array<string, string> $env
     *
     * @return int the exit status
     */
    public function run(array $args, string $cwd, array $env): int
    {
        if (['--help'] === $args || ['-h'] === $args || ['help'] === $args) {
            ($this->stdout)(self::USAGE);

            return 0;
        }
        $command = array_shift($args);
        if (null === $command) {
            ($this->stderr)(self::USAGE);

            return 2;
        }
        if ('generate' !== $command) {
            ($this->stderr)(sprintf("Unknown command \"%s\".\n\n%s", $command, self::USAGE));

            return 2;
        }

        try {
            $options = $this->parse($args);
            if (isset($options['help'])) {
                ($this->stdout)(self::USAGE);

                return 0;
            }

            return $this->generate($options, $cwd, $env);
        } catch (UsageError $e) {
            ($this->stderr)($e->getMessage() . "\n\n" . self::USAGE);

            return 2;
        } catch (GeneratorException $e) {
            ($this->stderr)('Error: ' . $e->getMessage() . "\n");

            return 2;
        } catch (MentionApiException $e) {
            ($this->stderr)('Error: the OpenAPI document could not be fetched: ' . $e->getMessage() . "\n");

            return 2;
        }
    }

    /**
     * @param list<string> $args
     *
     * @return array<string, string|bool>
     *
     * @throws UsageError
     */
    private function parse(array $args): array
    {
        $options = [];
        for ($i = 0, $count = \count($args); $i < $count; ++$i) {
            if (1 !== preg_match('/^--([a-z]+)(?:=(.*))?$/s', $args[$i], $match)) {
                throw new UsageError(sprintf('Unexpected argument "%s".', $args[$i]));
            }
            $name = $match[1];
            if ('check' === $name || 'help' === $name) {
                if (isset($match[2])) {
                    throw new UsageError(sprintf('The option --%s takes no value.', $name));
                }
                $options[$name] = true;
                continue;
            }
            if (!\in_array($name, self::VALUE_OPTIONS, true)) {
                throw new UsageError(sprintf('Unknown option --%s.', $name));
            }
            if (isset($match[2])) {
                $options[$name] = $match[2];
            } elseif (isset($args[$i + 1]) && 0 !== strpos($args[$i + 1], '--')) {
                $options[$name] = $args[++$i];
            } else {
                throw new UsageError(sprintf('The option --%s needs a value.', $name));
            }
        }

        return $options;
    }

    /**
     * @param array<string, string|bool> $options
     * @param array<string, string>      $env
     */
    private function generate(array $options, string $cwd, array $env): int
    {
        $missing = array_values(array_filter(['namespace', 'dir'], static function (string $name) use ($options): bool {
            return !isset($options[$name]) || '' === $options[$name];
        }));
        if ([] !== $missing) {
            throw new UsageError('Missing option: ' . implode(', ', array_map(static function (string $name): string {
                return '--' . $name;
            }, $missing)) . '.');
        }

        $target = $this->target($options, $cwd);
        $document = $this->document($options, $cwd, $env);
        $files = (new WrapperGenerator())->generate($document, (string) $options['namespace'], $target);
        $output = new OutputDirectory($this->path((string) $options['dir'], $cwd));
        $dir = (string) $options['dir'];

        if (isset($options['check'])) {
            $drift = $output->drift($files);
            if ($drift->isEmpty()) {
                ($this->stdout)(sprintf("The %d generated files in %s are up to date.\n", \count($files), $dir));

                return 0;
            }
            $report = sprintf("The generated files in %s no longer match the OpenAPI document:\n", $dir);
            foreach (['missing' => $drift->getMissing(), 'changed' => $drift->getChanged(), 'no longer generated' => $drift->getStale()] as $label => $paths) {
                foreach ($paths as $path) {
                    $report .= sprintf("  %s: %s\n", $label, $path);
                }
            }
            ($this->stderr)($report . "Run \"vendor/bin/mention-api-client generate\" with the same options and commit the result.\n");

            return 1;
        }

        $output->write($files);
        ($this->stdout)(sprintf("Wrote %d files to %s (PHP %s target).\n", \count($files), $dir, $target));

        return 0;
    }

    /**
     * @param array<string, string|bool> $options
     */
    private function target(array $options, string $cwd): PhpTarget
    {
        if (isset($options['php'])) {
            return PhpTarget::fromString((string) $options['php']);
        }
        $file = $this->path(isset($options['composer']) ? (string) $options['composer'] : 'composer.json', $cwd);
        $contents = is_file($file) ? file_get_contents($file) : false;
        if (false === $contents) {
            throw new GeneratorException(sprintf('Cannot read %s to find the PHP version of your app: pass --php=7.4 or --php=8.2, or --composer=<file>.', $file));
        }
        $composer = json_decode($contents, true);
        if (!\is_array($composer)) {
            throw new GeneratorException(sprintf('%s is not valid JSON.', $file));
        }
        $requirement = $composer['require']['php'] ?? null;

        return \is_string($requirement) ? PhpTarget::fromRequirement($requirement) : PhpTarget::php74();
    }

    /**
     * @param array<string, string|bool> $options
     * @param array<string, string>      $env
     *
     * @return array<string, mixed>
     */
    private function document(array $options, string $cwd, array $env): array
    {
        if (isset($options['source'])) {
            $file = $this->path((string) $options['source'], $cwd);
            $contents = is_file($file) ? file_get_contents($file) : false;
            if (false === $contents) {
                throw new GeneratorException(sprintf('Cannot read %s.', $file));
            }
            $document = json_decode($contents, true);
            if (!\is_array($document)) {
                throw new GeneratorException(sprintf('%s is not valid JSON.', $file));
            }

            return $document;
        }

        if (isset($options['client'])) {
            $file = $this->path((string) $options['client'], $cwd);
            if (!is_file($file)) {
                throw new GeneratorException(sprintf('Cannot read %s.', $file));
            }
            $client = (static function (string $file) {
                return require $file;
            })($file);
            if (!$client instanceof MentionApiClient) {
                throw new GeneratorException(sprintf('%s must return a Bannerstop\MentionApi\MentionApiClient.', $file));
            }
        } else {
            $client = EnvironmentClient::create($env);
        }

        return $client->openApi();
    }

    private function path(string $path, string $cwd): string
    {
        $absolute = '/' === ($path[0] ?? '') || 1 === preg_match('#^[A-Za-z]:[\\\\/]#', $path);

        return $absolute ? $path : rtrim($cwd, '/\\') . '/' . $path;
    }
}
