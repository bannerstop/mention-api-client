<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Tests;

use Bannerstop\MentionApi\Generator\PhpTarget;
use Bannerstop\MentionApi\Generator\WrapperGenerator;
use PHPUnit\Framework\TestCase;

/**
 * The generated code for the sample document, as committed in tests/Snapshots. A change of the generator shows up
 * as a diff of these files; regenerate them with UPDATE_SNAPSHOTS=1 vendor/bin/phpunit and review the diff.
 */
final class SnapshotTest extends TestCase
{
    private const NAMESPACE = 'Acme\\Mention';

    public function testThePhp74OutputMatchesTheSnapshot(): void
    {
        $this->assertMatchesSnapshot(PhpTarget::php74(), __DIR__ . '/Snapshots/php74');
    }

    public function testThePhp82OutputMatchesTheSnapshot(): void
    {
        $this->assertMatchesSnapshot(PhpTarget::php82(), __DIR__ . '/Snapshots/php82');
    }

    public function testTheSnapshotsOfEveryTargetPassTheLintOfThisPhp(): void
    {
        foreach (['php74' => 70400, 'php82' => 80200] as $directory => $minimum) {
            if (\PHP_VERSION_ID < $minimum) {
                continue;
            }
            foreach ($this->snapshotFiles(__DIR__ . '/Snapshots/' . $directory) as $file) {
                exec(escapeshellarg(\PHP_BINARY) . ' -d error_reporting=-1 -d display_errors=1 -l ' . escapeshellarg($file) . ' 2>&1', $output, $status);
                self::assertSame(0, $status, $file . ': ' . implode("\n", $output));
                $output = [];
            }
        }
    }

    private function assertMatchesSnapshot(PhpTarget $target, string $directory): void
    {
        $files = (new WrapperGenerator())->generate(GeneratedCode::fixtureDocument(), self::NAMESPACE, $target);

        if ('1' === getenv('UPDATE_SNAPSHOTS')) {
            foreach ($this->snapshotFiles($directory) as $old) {
                unlink($old);
            }
            foreach ($files as $path => $code) {
                if (!is_dir(\dirname($directory . '/' . $path))) {
                    mkdir(\dirname($directory . '/' . $path), 0777, true);
                }
                file_put_contents($directory . '/' . $path, $code);
            }
        }

        $expected = [];
        foreach ($this->snapshotFiles($directory) as $file) {
            $expected[substr($file, \strlen($directory) + 1)] = (string) file_get_contents($file);
        }
        ksort($expected, SORT_STRING);

        self::assertSame(array_keys($expected), array_keys($files));
        foreach ($files as $path => $code) {
            self::assertSame($expected[$path], $code, $path);
        }
    }

    /**
     * @return list<string>
     */
    private function snapshotFiles(string $directory): array
    {
        $files = [];
        if (is_dir($directory)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }
}
