<?php

declare(strict_types=1);

namespace Bannerstop\MentionApi\Tests;

use PHPUnit\Framework\TestCase;

/**
 * The executable itself: autoloading, arguments, environment and exit status.
 */
final class BinTest extends TestCase
{
    /** @var string */
    private $work;

    protected function setUp(): void
    {
        $this->work = sys_get_temp_dir() . '/mention-api-client-bin-' . getmypid() . '-' . bin2hex(random_bytes(4));
        mkdir($this->work);
        copy(__DIR__ . '/Fixtures/composer-php74.json', $this->work . '/composer.json');
        copy(__DIR__ . '/Fixtures/openapi.json', $this->work . '/openapi.json');
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->work));
    }

    /**
     * @param list<string> $args
     */
    private function bin(array $args, ?string &$stdout = null, ?string &$stderr = null): int
    {
        $command = escapeshellarg(\PHP_BINARY) . ' ' . escapeshellarg(\dirname(__DIR__) . '/bin/mention-api-client') . ' ' . implode(' ', array_map('escapeshellarg', $args));
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->work);
        self::assertIsResource($process);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return proc_close($process);
    }

    public function testTheExecutableGeneratesAndChecks(): void
    {
        $generate = ['generate', '--source=openapi.json', '--namespace=Acme\\Mention', '--dir=src/Mention'];

        self::assertSame(0, $this->bin($generate, $stdout, $stderr), $stderr);
        self::assertStringContainsString('Wrote 14 files', $stdout);
        self::assertFileExists($this->work . '/src/Mention/Query/GetInvoiceTotalsRow.php');

        self::assertSame(0, $this->bin(array_merge($generate, ['--check']), $stdout, $stderr), $stderr);

        file_put_contents($this->work . '/src/Mention/Queries.php', "<?php\n");
        self::assertSame(1, $this->bin(array_merge($generate, ['--check']), $stdout, $stderr));
        self::assertStringContainsString('changed: Queries.php', $stderr);
    }

    public function testTheExecutableReadsTheEnvironmentWithoutWarnings(): void
    {
        self::assertSame(2, $this->bin(['generate', '--namespace=Acme\\Mention', '--dir=out'], $stdout, $stderr));
        self::assertStringContainsString('MENTION_API_URL', $stderr);
        self::assertStringNotContainsString('Warning', $stderr);
        self::assertStringNotContainsString('Deprecated', $stderr);
    }
}
