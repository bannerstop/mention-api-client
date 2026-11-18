<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Tests;

use Bannerstop\MentionApi\Generator\OutputDirectory;
use Bannerstop\MentionApi\Generator\WrapperGenerator;
use PHPUnit\Framework\TestCase;

final class OutputDirectoryTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/mention-api-client-out-' . getmypid() . '-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        $this->remove($this->directory);
    }

    private function remove(string $path): void
    {
        if (is_file($path)) {
            unlink($path);

            return;
        }
        foreach (is_dir($path) ? array_diff(scandir($path) ?: [], ['.', '..']) : [] as $child) {
            $this->remove($path . '/' . $child);
        }
        if (is_dir($path)) {
            rmdir($path);
        }
    }

    private function generated(string $body = 'a'): string
    {
        return "<?php\n\n// " . WrapperGenerator::MARKER . "\n\n// " . $body . "\n";
    }

    public function testWriteCreatesTheDirectoryAndTheFiles(): void
    {
        $output = new OutputDirectory($this->directory . '/src/Mention');

        $output->write(['Queries.php' => $this->generated(), 'Query/FooRow.php' => $this->generated('row')]);

        self::assertSame($this->generated(), file_get_contents($this->directory . '/src/Mention/Queries.php'));
        self::assertSame($this->generated('row'), file_get_contents($this->directory . '/src/Mention/Query/FooRow.php'));
    }

    public function testWriteRemovesGeneratedFilesThatAreNoLongerNeeded(): void
    {
        $output = new OutputDirectory($this->directory);
        $output->write(['Queries.php' => $this->generated(), 'Query/GoneRow.php' => $this->generated('gone'), 'Routine/GoneParameters.php' => $this->generated('gone')]);

        $output->write(['Queries.php' => $this->generated()]);

        self::assertFileDoesNotExist($this->directory . '/Query/GoneRow.php');
        self::assertFileDoesNotExist($this->directory . '/Routine/GoneParameters.php');
        self::assertFileExists($this->directory . '/Queries.php');
    }

    public function testWriteNeverTouchesAFileThatIsNotGenerated(): void
    {
        $output = new OutputDirectory($this->directory);
        mkdir($this->directory . '/Query', 0777, true);
        file_put_contents($this->directory . '/Query/Mine.php', "<?php // written by hand\n");
        file_put_contents($this->directory . '/Notes.txt', 'keep');

        $output->write(['Queries.php' => $this->generated()]);

        self::assertFileExists($this->directory . '/Query/Mine.php');
        self::assertFileExists($this->directory . '/Notes.txt');
    }

    public function testDriftIsEmptyWhenTheDirectoryMatches(): void
    {
        $output = new OutputDirectory($this->directory);
        $files = ['Queries.php' => $this->generated(), 'Query/FooRow.php' => $this->generated('row')];
        $output->write($files);

        $drift = $output->drift($files);

        self::assertTrue($drift->isEmpty());
    }

    public function testDriftNamesMissingChangedAndStaleFiles(): void
    {
        $output = new OutputDirectory($this->directory);
        $output->write(['Queries.php' => $this->generated(), 'Query/OldRow.php' => $this->generated('old'), 'Query/SameRow.php' => $this->generated('same')]);
        file_put_contents($this->directory . '/Queries.php', $this->generated('edited'));

        $drift = $output->drift(['Queries.php' => $this->generated(), 'Query/NewRow.php' => $this->generated('new'), 'Query/SameRow.php' => $this->generated('same')]);

        self::assertFalse($drift->isEmpty());
        self::assertSame(['Query/NewRow.php'], $drift->getMissing());
        self::assertSame(['Queries.php'], $drift->getChanged());
        self::assertSame(['Query/OldRow.php'], $drift->getStale());
    }

    public function testDriftOfAMissingDirectoryListsEveryFileAsMissing(): void
    {
        $drift = (new OutputDirectory($this->directory . '/nope'))->drift(['Queries.php' => $this->generated()]);

        self::assertSame(['Queries.php'], $drift->getMissing());
    }

    public function testCheckingWritesNothing(): void
    {
        (new OutputDirectory($this->directory))->drift(['Queries.php' => $this->generated()]);

        self::assertDirectoryDoesNotExist($this->directory);
    }

    public function testWriteRefusesToOverwriteAFileThatIsNotGenerated(): void
    {
        $output = new OutputDirectory($this->directory);
        mkdir($this->directory . '/Query', 0777, true);
        file_put_contents($this->directory . '/Query/FooRow.php', "<?php // written by hand\n");

        try {
            $output->write(['Queries.php' => $this->generated(), 'Query/FooRow.php' => $this->generated('row')]);
            self::fail('Expected an exception.');
        } catch (\Bannerstop\MentionApi\Generator\GeneratorException $e) {
            self::assertStringContainsString('Query/FooRow.php', $e->getMessage());
        }
        self::assertSame("<?php // written by hand\n", file_get_contents($this->directory . '/Query/FooRow.php'));
        self::assertFileDoesNotExist($this->directory . '/Queries.php', 'nothing is written when one file is refused');
    }

    public function testStaleFilesAreFoundInADirectoryWithBracketsInItsPath(): void
    {
        $output = new OutputDirectory($this->directory . '/[app]');
        $output->write(['Query/GoneRow.php' => $this->generated('gone')]);

        $drift = $output->drift([]);

        self::assertSame(['Query/GoneRow.php'], $drift->getStale());
    }
}
